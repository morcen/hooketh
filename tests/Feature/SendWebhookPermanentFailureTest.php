<?php

namespace Tests\Feature;

use App\Jobs\SendWebhook;
use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SendWebhookPermanentFailureTest extends TestCase
{
    use RefreshDatabase;

    private function makeDelivery(User $user): Delivery
    {
        $event = Event::factory()->for($user)->create();
        $endpoint = Endpoint::factory()->for($user)->create(['url' => 'http://8.8.8.8/webhook', 'is_active' => true]);

        return Delivery::factory()->create([
            'event_id' => $event->id,
            'endpoint_id' => $endpoint->id,
            'status' => 'pending',
            'attempt_count' => 0,
            'next_retry_at' => null,
        ]);
    }

    /**
     * @return array<string, array{int}>
     */
    public static function permanentClientErrorStatusCodesProvider(): array
    {
        return [
            '400 Bad Request' => [400],
            '401 Unauthorized' => [401],
            '404 Not Found' => [404],
            '422 Unprocessable Entity' => [422],
            '499 highest 4xx' => [499],
        ];
    }

    #[DataProvider('permanentClientErrorStatusCodesProvider')]
    public function test_permanent_client_error_is_not_scheduled_for_retry(int $statusCode): void
    {
        Http::fake([
            '*' => Http::response('rejected', $statusCode),
        ]);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame($statusCode, $delivery->response_code);

        // A non-retryable client error should fail permanently on the first
        // attempt rather than being scheduled for the usual backoff/retry cycle.
        $this->assertNull($delivery->next_retry_at);
        $this->assertSame(0, Delivery::readyForRetry()->whereKey($delivery->id)->count());
    }

    /**
     * @return array<string, array{int}>
     */
    public static function transientStatusCodesProvider(): array
    {
        return [
            '408 Request Timeout' => [408],
            '429 Too Many Requests' => [429],
            '500 Internal Server Error' => [500],
            '503 Service Unavailable' => [503],
        ];
    }

    #[DataProvider('transientStatusCodesProvider')]
    public function test_transient_failure_is_still_scheduled_for_retry(int $statusCode): void
    {
        Http::fake([
            '*' => Http::response('try again later', $statusCode),
        ]);

        $delivery = $this->makeDelivery(User::factory()->withPersonalTeam()->create());

        $job = (new SendWebhook($delivery))->withFakeQueueInteractions();
        $job->handle();

        $job->assertNotReleased();

        $delivery->refresh();
        $this->assertSame('failed', $delivery->status);
        $this->assertSame(1, $delivery->attempt_count);
        $this->assertSame($statusCode, $delivery->response_code);

        // 408/429 and 5xx responses are legitimately transient, so the normal
        // backoff/retry schedule should still apply.
        $this->assertNotNull($delivery->next_retry_at);
    }
}
