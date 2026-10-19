<?php

namespace Tests\Unit;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use Tests\TestCase;

class SendWebhookMaxExceptionsRemovedTest extends TestCase
{
    /**
     * Regression test for #132: $maxExceptions was dead configuration.
     * handle() catches Throwable (not just Exception) and always handles
     * failures internally via handleFailedDelivery(), so no exception ever
     * escapes to be counted against Laravel's maxExceptions ceiling. The
     * retry ceiling is entirely driven by Delivery.attempt_count vs.
     * config('webhooks.max_retries'), via tries()/maxAttempts().
     */
    public function test_max_exceptions_property_does_not_exist(): void
    {
        $job = new SendWebhook(new Delivery);

        $this->assertFalse(property_exists($job, 'maxExceptions'));
    }
}
