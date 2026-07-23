<?php

namespace Tests\Unit;

use App\Rules\SafeWebhookUrl;
use Tests\TestCase;

class SafeWebhookUrlTest extends TestCase
{
    public function test_public_ip_literal_url_is_safe(): void
    {
        $this->assertTrue(SafeWebhookUrl::isUrlSafe('https://8.8.8.8/webhook'));
    }

    public function test_loopback_ip_literal_url_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://127.0.0.1/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[::1]/webhook'));
    }

    public function test_private_range_ip_literal_url_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://10.0.0.5/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://172.16.0.5/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://192.168.1.5/webhook'));
    }

    public function test_link_local_and_cloud_metadata_ip_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://169.254.169.254/latest/meta-data/'));
    }

    public function test_non_http_scheme_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('ftp://8.8.8.8/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('file:///etc/passwd'));
    }

    public function test_unresolvable_hostname_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://this-host-should-never-resolve.invalid/webhook'));
    }

    public function test_cgnat_shared_address_space_is_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://100.64.0.1/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://100.100.100.100/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://100.127.255.254/webhook'));
    }

    public function test_addresses_adjacent_to_cgnat_range_are_still_safe(): void
    {
        $this->assertTrue(SafeWebhookUrl::isUrlSafe('http://100.63.255.255/webhook'));
        $this->assertTrue(SafeWebhookUrl::isUrlSafe('http://100.128.0.0/webhook'));
    }

    public function test_other_iana_special_purpose_ranges_are_unsafe(): void
    {
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://192.0.0.5/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://192.0.2.5/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://198.18.5.5/webhook'));
    }

    public function test_resolve_safe_ip_returns_the_ip_for_a_safe_url(): void
    {
        $this->assertSame('8.8.8.8', SafeWebhookUrl::resolveSafeIp('https://8.8.8.8/webhook'));
    }

    public function test_resolve_safe_ip_returns_null_for_an_unsafe_url(): void
    {
        $this->assertNull(SafeWebhookUrl::resolveSafeIp('http://100.64.0.1/webhook'));
    }

    public function test_ipv4_embedded_in_ipv6_transition_prefixes_are_unsafe(): void
    {
        // NAT64 well-known prefix (RFC 6052) embedding 127.0.0.1 and the cloud metadata IP.
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[64:ff9b::7f00:1]/webhook'));
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[64:ff9b::a9fe:a9fe]/webhook'));

        // NAT64 local-use prefix (RFC 8215).
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[64:ff9b:1::7f00:1]/webhook'));

        // 6to4 (RFC 3056) embedding 127.0.0.1.
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[2002:7f00:0001::]/webhook'));

        // Teredo (RFC 4380).
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[2001::7f00:1]/webhook'));

        // Deprecated IPv4-compatible IPv6.
        $this->assertFalse(SafeWebhookUrl::isUrlSafe('http://[::127.0.0.1]/webhook'));
    }

    public function test_public_ipv6_address_outside_transition_prefixes_is_still_safe(): void
    {
        $this->assertTrue(SafeWebhookUrl::isUrlSafe('http://[2001:4860:4860::8888]/webhook'));
    }
}
