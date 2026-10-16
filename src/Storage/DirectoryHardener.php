<?php

declare(strict_types=1);

namespace UploadHelper\Storage;

class DirectoryHardener
{
    /**
     * Tự động khóa bảo mật thư mục lưu trữ tải lên
     * - Sinh file .htaccess vô hiệu hóa engine PHP và cấm thực thi script (Apache/LiteSpeed)
     * - Sinh file web.config xóa bỏ toàn bộ script handlers (IIS)
     * - Sinh file index.html ngăn chặn Directory Listing (duyệt thư mục)
     */
    public static function harden(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $realDir = realpath($directory);
        if ($realDir === false) {
            return;
        }

        // 1. Tạo file .htaccess cho Apache / LiteSpeed
        $htaccessPath = $realDir . DIRECTORY_SEPARATOR . '.htaccess';
        if (!file_exists($htaccessPath)) {
            $htaccessContent = <<<HTACCESS
# Prevent script execution inside the upload directory
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

# Deny direct access to dangerous file extensions
<FilesMatch "(?i:\.(php|phtml|phar|phps|pht|sh|pl|py|cgi|asp|aspx|jsp|exe|bat|cmd|vbs|env|ini|htaccess|htpasswd|git))$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Disable directory listing
Options -Indexes
HTACCESS;
            @file_put_contents($htaccessPath, $htaccessContent);
        }

        // 2. Tạo file web.config cho Microsoft IIS
        $webConfigPath = $realDir . DIRECTORY_SEPARATOR . 'web.config';
        if (!file_exists($webConfigPath)) {
            $webConfigContent = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<configuration>
    <system.webServer>
        <handlers>
            <clear />
        </handlers>
        <directoryBrowse enabled="false" />
    </system.webServer>
</configuration>
XML;
            @file_put_contents($webConfigPath, $webConfigContent);
        }

        // 3. Tạo file index.html ngăn chặn duyệt thư mục trên Nginx hoặc máy chủ chưa tắt Indexes
        $indexPath = $realDir . DIRECTORY_SEPARATOR . 'index.html';
        if (!file_exists($indexPath)) {
            $indexContent = "<!DOCTYPE html><html><head><title>403 Forbidden</title></head><body><h1>Directory listing is forbidden.</h1></body></html>\n";
            @file_put_contents($indexPath, $indexContent);
        }
    }
}
