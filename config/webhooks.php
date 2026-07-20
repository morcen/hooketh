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
    | Webhook Payload Max Size
    |--------------------------------------------------------------------------
    | Maximum size, in bytes, of a trigger request's JSON-encoded payload.
    | Prevents a single trigger from being amplified into excessive
    | bandwidth/CPU cost across every active endpoint and retry attempt.
    */
    'payload_max_size' => (int) env('WEBHOOK_PAYLOAD_MAX_SIZE', 102400),

];
