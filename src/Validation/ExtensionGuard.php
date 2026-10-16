<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Exceptions\ValidationException;

class ExtensionGuard
{
    /** @var array<int, string> */
    public const DANGEROUS_EXTENSIONS = [
        'php', 'php2', 'php3', 'php4', 'php5', 'php6', 'php7', 'php8',
        'phtml', 'phar', 'phps', 'inc', 'pht', 'pgif',
        'sh', 'bash', 'zsh', 'bin', 'command',
        'pl', 'py', 'pyc', 'pyo', 'cgi', 'asp', 'aspx', 'jsp', 'jspx', 'cfm',
        'exe', 'bat', 'cmd', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh',
        'com', 'scr', 'pif', 'msi', 'msp',
        'htaccess', 'htpasswd', 'ini', 'env', 'config',
    ];

    /**
     * Làm sạch phần mở rộng, loại bỏ null bytes, khoảng trắng, dấu chấm thừa
     */
    public static function cleanExtension(string $extension): string
    {
        // Loại bỏ null bytes (\0, %00)
        $clean = str_replace(["\0", '%00'], '', $extension);

        // Loại bỏ Windows alternate data stream (::$DATA)
        if (str_contains($clean, '::$DATA')) {
            $clean = str_replace('::$DATA', '', $clean);
        }

        // Cắt khoảng trắng và dấu chấm đầu cuối
        $clean = trim($clean, " .\t\n\r\0\x0B");

        return strtolower($clean);
    }

    /**
     * Kiểm tra phần mở rộng có nằm trong danh sách trắng cho phép hay không
     *
     * @param array<int, string> $whitelist
     * @throws ValidationException
     * @throws SecurityException
     */
    public static function assertAllowed(string $filename, array $whitelist): void
    {
        // 1. Kiểm tra null-byte injection trong toàn bộ tên file
        if (str_contains($filename, "\0") || str_contains($filename, '%00')) {
            throw SecurityException::forCode('ERR_SECURITY_NULL_BYTE', ['filename' => $filename]);
        }

        // 2. Tách extension thực tế
        $cleanFilename = self::cleanExtension($filename);
        $ext = strtolower(pathinfo($cleanFilename, PATHINFO_EXTENSION));

        // 3. Chặn cứng các extension thực thi nguy hiểm
        if (in_array($ext, self::DANGEROUS_EXTENSIONS, true)) {
            throw SecurityException::forCode('ERR_SECURITY_DANGEROUS_EXTENSION', ['filename' => $filename, 'extension' => $ext]);
        }

        // 4. Kiểm tra double extension nguy hiểm (ví dụ: avatar.php.jpg)
        $parts = explode('.', $cleanFilename);
        if (count($parts) > 2) {
            // Duyệt qua các phần mở rộng trung gian
            for ($i = 1; $i < count($parts) - 1; $i++) {
                $subExt = strtolower(trim($parts[$i], " \t\n\r\0\x0B"));
                if (in_array($subExt, self::DANGEROUS_EXTENSIONS, true)) {
                    throw SecurityException::forCode('ERR_SECURITY_DOUBLE_EXTENSION', ['filename' => $filename, 'dangerous_sub_ext' => $subExt]);
                }
            }
        }

        // 5. Nếu có whitelist được định nghĩa, kiểm tra xem extension có nằm trong whitelist không
        if (!empty($whitelist)) {
            $normalizedWhitelist = array_map(fn($item) => strtolower(ltrim(trim($item), '.')), $whitelist);
            if (!in_array($ext, $normalizedWhitelist, true)) {
                throw ValidationException::forCode('ERR_EXTENSION_NOT_ALLOWED', ['filename' => $filename, 'extension' => $ext, 'allowed' => $normalizedWhitelist]);
            }
        }
    }
}
