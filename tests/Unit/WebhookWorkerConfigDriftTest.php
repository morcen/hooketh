<?php

namespace Tests\Unit;

use Tests\TestCase;

class WebhookWorkerConfigDriftTest extends TestCase
{
    /**
     * Regression test for #90: the queue worker's --tries CLI flag has no
     * effect on the SendWebhook job (it defines its own tries()/backoff(),
     * see SendWebhookTriesIsConfigDrivenTest), yet Dockerfile,
     * WEBHOOK_README.md, and LARAVEL-CLOUD.md kept documenting/hardcoding
     * mismatched --tries values. Pin that none of the worker command
     * examples reintroduce the dead flag.
     */
    public function test_queue_worker_command_examples_do_not_hardcode_a_tries_flag(): void
    {
        $filesWithWorkerCommands = [
            base_path('Dockerfile'),
            base_path('webhook-worker.conf'),
            base_path('WEBHOOK_README.md'),
            base_path('LARAVEL-CLOUD.md'),
        ];

        foreach ($filesWithWorkerCommands as $file) {
            $contents = file_get_contents($file);

            $this->assertNotFalse($contents, "Could not read {$file}");
            $this->assertDoesNotMatchRegularExpression(
                '/queue:work[^\n]*--tries=\d+/',
                $contents,
                "{$file} hardcodes a --tries flag on the webhooks queue worker, but SendWebhook's own tries()/backoff() always override it."
            );
        }
    }

    public function test_webhook_worker_conf_does_not_reference_the_stale_project_path(): void
    {
        $contents = file_get_contents(base_path('webhook-worker.conf'));

        $this->assertNotFalse($contents);
        $this->assertStringNotContainsString('webhook-management-platform', $contents);
    }
}
