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
    /**
     * IANA special-purpose ranges not excluded by PHP's
     * FILTER_FLAG_NO_PRIV_RANGE / FILTER_FLAG_NO_RES_RANGE flags, plus IPv6
     * transition mechanisms that can embed an arbitrary IPv4 address in
     * their low bits. Blocking these prefixes outright prevents an attacker
     * from smuggling a loopback/private/link-local IPv4 target past the
     * IPv4-only checks below via a NAT64, 6to4, Teredo, or IPv4-compatible
     * IPv6 literal.
     *
     * @var array<int, string>
     */
    private const EXTRA_BLOCKED_RANGES = [
        '100.64.0.0/10', // RFC 6598 shared address space (CGNAT)
        '192.0.0.0/24',  // IETF protocol assignments
        '192.0.2.0/24',  // TEST-NET-1
        '198.18.0.0/15', // benchmarking
        '64:ff9b::/96',  // RFC 6052 NAT64 well-known prefix
        '64:ff9b:1::/48', // RFC 8215 NAT64 local-use prefix
        '2002::/16',     // RFC 3056 6to4
        '2001::/32',     // RFC 4380 Teredo
        '::/96',         // deprecated IPv4-compatible IPv6 (::a.b.c.d)
    ];

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
        return self::resolveSafeIp($url) !== null;
    }

    /**
     * Determine whether every IP address a host resolves to is publicly
     * routable (i.e. not loopback, link-local, private, or reserved).
     */
    public static function isHostSafe(string $host): bool
    {
        return self::resolveSafeHostIp($host) !== null;
    }

    /**
     * Resolve a webhook URL's host to a single validated, publicly routable
     * IP address, or null if the URL is unsafe/unresolvable.
     *
     * Callers making the actual outbound request should connect directly to
     * this IP (e.g. via curl's CURLOPT_RESOLVE) rather than letting the HTTP
     * client re-resolve the hostname, to avoid a DNS-rebinding race between
     * validation and the request itself.
     */
    public static function resolveSafeIp(string $url): ?string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? null;

        if (! in_array($scheme, ['http', 'https'], true) || ! $host) {
            return null;
        }

        return self::resolveSafeHostIp($host);
    }

    private static function resolveSafeHostIp(string $host): ?string
    {
        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolveHost($host);

        if (empty($ips)) {
            return null;
        }

        foreach ($ips as $ip) {
            if (! self::isIpSafe($ip)) {
                return null;
            }
        }

        return $ips[0];
    }

    private static function isIpSafe(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED_RANGES as $range) {
            if (self::ipInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = explode('/', $cidr);

        $ipBin = inet_pton($ip);
        $subnetBin = inet_pton($subnet);

        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }

        $bits = (int) $bits;
        $bytes = intdiv($bits, 8);
        $remainderBits = $bits % 8;

        if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }

        if ($remainderBits === 0) {
            return true;
        }

        $mask = chr((0xFF << (8 - $remainderBits)) & 0xFF);

        return (substr($ipBin, $bytes, 1) & $mask) === (substr($subnetBin, $bytes, 1) & $mask);
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
