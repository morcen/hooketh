<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webhook Max Retries
    |--------------------------------------------------------------------------
    | Maximum number of times a failed webhook delivery will be retried.
    */
    'max_retries' => (int) env('WEBHOOK_MAX_RETRIES', 5),

    /*
    |--------------------------------------------------------------------------
    | Webhook Backoff Delays
    |--------------------------------------------------------------------------
    | Comma-separated delay in seconds for each retry attempt.
    | Default: 1min, 5min, 15min, 30min, 1hour
    */
    'backoff_delays' => array_map(
        'intval',
        explode(',', env('WEBHOOK_BACKOFF_DELAYS', '60,300,900,1800,3600'))
    ),

    /*
    |--------------------------------------------------------------------------
    | Webhook Trigger Rate Limit
    |--------------------------------------------------------------------------
    | Max trigger requests per minute per authenticated user.
    */
    'rate_limit' => (int) env('WEBHOOK_RATE_LIMIT', 60),

    /*
    |--------------------------------------------------------------------------
    | Secret Rotation Rate Limit
    |--------------------------------------------------------------------------
    | Max endpoint secret-regeneration requests per minute per authenticated
    | user. Kept low since every rotation invalidates the previous HMAC
    | signing secret, breaking signature verification for the receiver.
    */
    'secret_rotation_rate_limit' => (int) env('WEBHOOK_SECRET_ROTATION_RATE_LIMIT', 5),

    /*
    |--------------------------------------------------------------------------
    | Webhook Payload Max Size
    |--------------------------------------------------------------------------
    | Maximum size, in bytes, of a trigger request's JSON-encoded payload.
    | Prevents a single trigger from being amplified into excessive
    | bandwidth/CPU cost across every active endpoint and retry attempt.
    */
    'payload_max_size' => (int) env('WEBHOOK_PAYLOAD_MAX_SIZE', 102400),

    /*
    |--------------------------------------------------------------------------
    | Webhook Response Body Max Size
    |--------------------------------------------------------------------------
    | Maximum size, in bytes, of a delivery response body that will be
    | downloaded and stored. Prevents a malicious or misbehaving endpoint
    | from exhausting queue worker memory or unboundedly growing the
    | deliveries table by returning an arbitrarily large response.
    */
    'response_body_max_size' => (int) env('WEBHOOK_RESPONSE_BODY_MAX_SIZE', 65536),

    /*
    |--------------------------------------------------------------------------
    | Stuck "Retrying" Delivery Threshold
    |--------------------------------------------------------------------------
    | Minutes a Delivery may remain in the `retrying` status before it's
    | considered abandoned (e.g. the queue worker crashed or was restarted
    | mid-attempt) and swept back into the normal retry flow by
    | webhooks:process-retries.
    */
    'stuck_retrying_minutes' => (int) env('WEBHOOK_STUCK_RETRYING_MINUTES', 10),

];
