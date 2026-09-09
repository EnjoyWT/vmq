<?php

namespace app\service;

final class SafeHttpClient
{
    public static function get(string $url): string
    {
        return self::request($url, []);
    }

    public static function postJson(string $url, string $body, array $headers = []): string
    {
        return self::request($url, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => array_merge([
                'Content-Type: application/json',
                'Accept: text/plain, application/json;q=0.9, */*;q=0.1',
            ], $headers),
        ]);
    }

    private static function request(string $url, array $options): string
    {
        $target = self::resolvePublicTarget($url);
        if ($target === null) {
            return 'error: unsafe callback url';
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, $options + [
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_ENCODING => '',
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => 'Vmq/2.0 callback',
            CURLOPT_HTTPHEADER => ['Accept: text/plain, application/json;q=0.9, */*;q=0.1'],
            CURLOPT_RESOLVE => [$target['resolve']],
        ]);

        $body = curl_exec($ch);
        if ($body === false) {
            $body = 'error: ' . curl_error($ch);
        } elseif ((int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) < 200 || (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE) >= 300) {
            $body = 'error: http ' . curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        }
        curl_close($ch);

        return trim((string) $body);
    }

    public static function isPublicUrl(string $url): bool
    {
        return self::resolvePublicTarget($url) !== null;
    }

    private static function resolvePublicTarget(string $url): ?array
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(rtrim((string) $parts['host'], '.'));
        if ($host === '' || $host === 'localhost') {
            return null;
        }
        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            return null;
        }

        $ips = [];
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips[] = $host;
        } else {
            if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $host)) {
                return null;
            }
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            foreach (is_array($records) ? $records : [] as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
            if (!self::allPublicIps($ips)) {
                $ips = self::resolveWithCloudflareDoh($host);
            }
        }

        $ips = array_values(array_unique($ips));
        if (!self::allPublicIps($ips)) {
            return null;
        }

        $ip = $ips[0];
        $resolvedIp = str_contains($ip, ':') ? '[' . $ip . ']' : $ip;

        return ['resolve' => $host . ':' . $port . ':' . $resolvedIp];
    }

    private static function allPublicIps(array $ips): bool
    {
        if ($ips === []) {
            return false;
        }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }
        return true;
    }

    private static function resolveWithCloudflareDoh(string $host): array
    {
        $url = 'https://cloudflare-dns.com/dns-query?name=' . rawurlencode($host) . '&type=A';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Accept: application/dns-json'],
            CURLOPT_RESOLVE => ['cloudflare-dns.com:443:1.1.1.1'],
        ]);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($body === false || $status !== 200) {
            return [];
        }
        $payload = json_decode((string) $body, true);
        if (!is_array($payload) || (int) ($payload['Status'] ?? -1) !== 0) {
            return [];
        }

        $ips = [];
        foreach ($payload['Answer'] ?? [] as $answer) {
            if ((int) ($answer['type'] ?? 0) === 1 && filter_var($answer['data'] ?? '', FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = (string) $answer['data'];
            }
        }
        return array_values(array_unique($ips));
    }
}
