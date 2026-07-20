<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects webhook trigger payloads whose JSON-encoded size exceeds the
 * configured limit, preventing a single trigger request from being
 * amplified into excessive bandwidth/CPU cost across every active
 * endpoint and retry attempt.
 */
class WebhookPayloadSize implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $maxBytes = (int) config('webhooks.payload_max_size');
        $size = strlen(json_encode($value) ?: '');

        if ($size > $maxBytes) {
            $fail("The :attribute must not exceed {$maxBytes} bytes when JSON-encoded.");
        }
    }
}
