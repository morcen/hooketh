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
}
