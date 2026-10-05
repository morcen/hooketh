<?php

namespace Tests\Unit;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use Tests\TestCase;

class SendWebhookTriesIsConfigDrivenTest extends TestCase
{
    /**
     * Regression test for #122: the queue worker's --tries CLI flag has no
     * effect on this job because SendWebhook::tries() always overrides it.
     * The only thing that may change the retry ceiling is
     * config('webhooks.max_retries'); pin that here so webhook-worker.conf's
     * and the Makefile's --tries flags don't quietly get reintroduced under
     * the mistaken belief that they do anything.
     */
    public function test_tries_and_max_attempts_track_configured_max_retries_only(): void
    {
        config(['webhooks.max_retries' => 2]);

        $this->assertSame(3, SendWebhook::maxAttempts());
        $this->assertSame(3, (new SendWebhook(new Delivery()))->tries());

        config(['webhooks.max_retries' => 7]);

        $this->assertSame(8, SendWebhook::maxAttempts());
        $this->assertSame(8, (new SendWebhook(new Delivery()))->tries());
    }
}
