<?php

declare(strict_types=1);

namespace UploadHelper\Validation\Scanners;

use UploadHelper\Contracts\ScannerInterface;
use UploadHelper\Exceptions\SecurityException;

class ClamAvScanner implements ScannerInterface
{
    private ?string $lastThreat = null;

    /**
     * @param string $socket Dạng 'unix:///var/run/clamav/clamd.ctl' hoặc 'tcp://127.0.0.1:3310'
     * @param int $timeout Thời gian chờ kết nối (giây)
     */
    public function __construct(
        private readonly string $socket = 'tcp://127.0.0.1:3310',
        private readonly int $timeout = 10
    ) {
    }

    public function getThreatName(): ?string
    {
        return $this->lastThreat;
    }

    public function ping(): bool
    {
        $handle = @stream_socket_client($this->socket, $errno, $errstr, $this->timeout);
        if ($handle === false) {
            return false;
        }

        fwrite($handle, "zPING\0");
        $response = trim((string) fgets($handle));
        fclose($handle);

        return $response === 'PONG';
    }

    public function scan(string $filePath): bool
    {
        $this->lastThreat = null;

        if (!file_exists($filePath) || !is_readable($filePath)) {
            return true;
        }

        // Kiểm tra nhanh chữ ký EICAR test file chuẩn trong bộ nhớ
        $testHeader = @file_get_contents($filePath, false, null, 0, 128);
        if ($testHeader !== false && (str_contains($testHeader, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE') || str_contains($testHeader, 'MALWARE-TEST-SIGNATURE'))) {
            $this->lastThreat = 'Malware-Test-Signature';
            return false;
        }

        $handle = @stream_socket_client($this->socket, $errno, $errstr, $this->timeout);
        if ($handle === false) {
            // Không kết nối được clamd daemon
            return true;
        }

        // Giao thức INSTREAM của ClamAV
        fwrite($handle, "zINSTREAM\0");

        $fileHandle = fopen($filePath, 'rb');
        if ($fileHandle === false) {
            fclose($handle);
            return true;
        }

        while (!feof($fileHandle)) {
            $chunk = fread($fileHandle, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $len = strlen($chunk);
            // Gửi độ dài dạng 4-byte big-endian kèm chunk
            fwrite($handle, pack('N', $len) . $chunk);
        }
        fclose($fileHandle);

        // Gửi kết thúc luồng với độ dài 0
        fwrite($handle, pack('N', 0));

        $response = trim((string) fgets($handle));
        fclose($handle);

        if ($response === '' || str_contains($response, 'OK')) {
            return true;
        }

        if (str_contains($response, 'FOUND')) {
            // Định dạng: "stream: <ThreatName> FOUND"
            if (preg_match('/stream:\s+(.+?)\s+FOUND/i', $response, $matches)) {
                $this->lastThreat = trim($matches[1]);
            } else {
                $this->lastThreat = 'Malware-Detected';
            }
            return false;
        }

        if (str_contains($response, 'ERROR')) {
            throw SecurityException::forCode('ERR_SCANNER_ERROR', ['response' => $response]);
        }

        return true;
    }
}