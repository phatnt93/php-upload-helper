<?php

declare(strict_types=1);

namespace UploadHelper\Validation;

use UploadHelper\Exceptions\SecurityException;

class SsrfGuard
{
    /** Danh sách các cổng thông thường cho phép tải tệp từ xa */
    public const ALLOWED_PORTS = [80, 443, 8080, 8443];

    /**
     * Xác thực URL để đảm bảo an toàn trước tấn công Server-Side Request Forgery (SSRF)
     *
     * @param (callable(string): array<int, string>)|null $resolver Custom DNS resolver (defaults to resolveHostIps())
     * @return list<string> The vetted IP addresses of the host; callers should connect to one of them
     *                      instead of resolving the host again (prevents DNS rebinding).
     *
     * @throws SecurityException Nếu phát hiện URL nhắm tới IP nội bộ, loopback, hoặc metadata cloud
     */
    public static function assertSafeUrl(string $url, bool $allowPrivate = false, ?callable $resolver = null): array
    {
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'])) {
            throw SecurityException::forCode('ERR_SSRF_INVALID_URL', ['url' => $url]);
        }

        // 1. Chỉ cho phép giao thức HTTP và HTTPS
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            throw SecurityException::forCode('ERR_SSRF_DISALLOWED_SCHEME', ['url' => $url, 'scheme' => $scheme]);
        }

        if (!isset($parts['host'])) {
            throw SecurityException::forCode('ERR_SSRF_INVALID_URL', ['url' => $url]);
        }

        // 2. Kiểm tra cổng (Port) nếu có chỉ định
        if (isset($parts['port']) && !in_array((int) $parts['port'], self::ALLOWED_PORTS, true)) {
            throw SecurityException::forCode('ERR_SSRF_DISALLOWED_PORT', ['url' => $url, 'port' => (int) $parts['port']]);
        }

        $host = trim($parts['host'], '[]');

        // Chặn trực tiếp localhost và các biến thể thông dụng
        $lowerHost = strtolower($host);
        if (in_array($lowerHost, ['localhost', 'localhost.localdomain', 'local', 'broadcasthost'], true)) {
            if (!$allowPrivate) {
                throw SecurityException::forCode('ERR_SSRF_BLOCKED', ['url' => $url, 'host' => $host]);
            }
        }

        // 3. Phân giải DNS tìm tất cả IP tương ứng (an IP literal is never passed to a resolver)
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $resolved = [$host];
        } else {
            $resolved = $resolver !== null ? $resolver($host) : self::resolveHostIps($host);
        }

        $ips = array_values(array_filter(
            $resolved,
            static fn (mixed $ip): bool => is_string($ip) && filter_var($ip, FILTER_VALIDATE_IP) !== false
        ));
        if (empty($ips)) {
            throw SecurityException::forCode('ERR_SSRF_DNS_RESOLVE_FAILED', ['url' => $url, 'host' => $host]);
        }

        // 4. Kiểm tra từng IP được phân giải
        if (!$allowPrivate) {
            foreach ($ips as $ip) {
                if (self::isPrivateOrReservedIp($ip)) {
                    throw SecurityException::forCode('ERR_SSRF_BLOCKED', ['url' => $url, 'resolved_ip' => $ip]);
                }
            }
        }

        return $ips;
    }

    /**
     * @return array<int, string>
     */
    public static function resolveHostIps(string $host): array
    {
        // Nếu host đã là một địa chỉ IP hợp lệ
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $ips = [];

        // Thử lấy IPv4
        $ipv4s = @gethostbynamel($host);
        if (is_array($ipv4s)) {
            foreach ($ipv4s as $ip) {
                $ips[] = $ip;
            }
        }

        // Thử lấy bản ghi A và AAAA qua DNS
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($host, DNS_A + DNS_AAAA);
            if (is_array($records)) {
                foreach ($records as $record) {
                    if (isset($record['ip'])) {
                        $ips[] = $record['ip'];
                    } elseif (isset($record['ipv6'])) {
                        $ips[] = $record['ipv6'];
                    }
                }
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Kiểm tra một IP có thuộc dải Private, Loopback, Link-Local hay Metadata không
     */
    public static function isPrivateOrReservedIp(string $ip): bool
    {
        // 1. Kiểm tra IPv4 hoặc IPv6 cơ bản qua filter_var
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return true;
        }

        // 2. Kiểm tra IPv4 đặc thù
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($ip);
            if ($long === false) {
                return true;
            }

            // 127.0.0.0/8 (Loopback)
            if (($long & 0xFF000000) === 0x7F000000) {
                return true;
            }
            // 10.0.0.0/8 (Private A)
            if (($long & 0xFF000000) === 0x0A000000) {
                return true;
            }
            // 172.16.0.0/12 (Private B)
            if (($long & 0xFFF00000) === 0xAC100000) {
                return true;
            }
            // 192.168.0.0/16 (Private C)
            if (($long & 0xFFFF0000) === 0xC0A80000) {
                return true;
            }
            // 169.254.0.0/16 (Link-Local & Cloud Metadata AWS 169.254.169.254)
            if (($long & 0xFFFF0000) === 0xA9FE0000) {
                return true;
            }
            // 0.0.0.0/8
            if (($long & 0xFF000000) === 0x00000000) {
                return true;
            }
            // 100.64.0.0/10 (Carrier-Grade NAT)
            if (($long & 0xFFC00000) === 0x64400000) {
                return true;
            }

            return false;
        }

        // 3. Kiểm tra IPv6 đặc thù
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hex = bin2hex((string) inet_pton($ip));
            // ::1 (Loopback)
            if ($hex === str_repeat('0', 31) . '1') {
                return true;
            }
            // :: (Unspecified)
            if ($hex === str_repeat('0', 32)) {
                return true;
            }
            // fc00::/7 (Unique Local Address)
            if (str_starts_with($hex, 'fc') || str_starts_with($hex, 'fd')) {
                return true;
            }
            // fe80::/10 (Link-Local)
            if (str_starts_with($hex, 'fe8') || str_starts_with($hex, 'fe9') || str_starts_with($hex, 'fea') || str_starts_with($hex, 'feb')) {
                return true;
            }
            // IPv4-mapped IPv6 (::ffff:127.0.0.1)
            if (str_starts_with($hex, '00000000000000000000ffff')) {
                $v4Hex = substr($hex, 24);
                $v4Long = hexdec($v4Hex);
                $v4Ip = long2ip((int) $v4Long);
                return self::isPrivateOrReservedIp($v4Ip);
            }
        }

        return false;
    }
}
