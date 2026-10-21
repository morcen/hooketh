<?php

namespace Tests;

use App\Rules\SafeWebhookUrl;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Endpoint URLs throughout the suite use realistic hostnames (e.g.
        // example.com, hooks.slack.com) that SafeWebhookUrl's SSRF check
        // resolves via live DNS. Fake that resolution to a fixed, known-safe
        // public IP so these tests stay deterministic and don't depend on
        // the environment's network/DNS access. Tests exercising SSRF
        // blocking use IP literals directly (bypassing DNS entirely) or
        // install their own resolver override, so this default doesn't
        // affect them.
        SafeWebhookUrl::resolveHostUsing(fn (string $host) => ['8.8.8.8']);
    }

    protected function tearDown(): void
    {
        SafeWebhookUrl::resolveHostNormally();

        parent::tearDown();
    }
}
