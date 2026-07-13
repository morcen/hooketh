<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects webhook endpoint URLs that would cause the server to make
 * outbound requests to loopback, link-local, private, or otherwise
 * reserved network addresses (SSRF prevention).
 */
class SafeWebhookUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isUrlSafe($value)) {
            $fail('The :attribute must be a publicly routable HTTP or HTTPS URL.');
        }
    }

    /**
     * Determine whether a URL's scheme is HTTP(S) and its host resolves
     * only to publicly routable IP addresses.
     */
    public static function isUrlSafe(string $url): bool
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            return false;
        }

        return self::isHostSafe($host);
    }

    /**
     * Determine whether every IP address a host resolves to is publicly
     * routable (i.e. not loopback, link-local, private, or reserved).
     */
    public static function isHostSafe(string $host): bool
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolveHost($host);

        if (empty($ips)) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private static function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if ($records === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (array $record) => $record['ip'] ?? $record['ipv6'] ?? null,
            $records
        )));
    }
}
