<?php

namespace Tests\Feature;

use App\Models\Delivery;
use App\Models\Endpoint;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DeliveryDateRangeFilterBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_deliveries_table_has_an_index_on_created_at(): void
    {
        $indexedColumns = collect(Schema::getIndexes('deliveries'))
            ->pluck('columns')
            ->flatten()
            ->all();

        $this->assertContains('created_at', $indexedColumns);
    }

    public function test_api_date_range_filter_includes_the_full_first_and_last_day(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();

        $startOfFirstDay = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-06-01 00:00:00',
        ]);
        $endOfLastDay = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-06-30 23:59:59',
        ]);
        $beforeRange = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-05-31 23:59:59',
        ]);
        $afterRange = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-07-01 00:00:00',
        ]);

        $response = $this->actingAs($user)->getJson(
            '/api/v1/deliveries?from_date=2026-06-01&to_date=2026-06-30'
        );

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id');

        $this->assertTrue($ids->contains($startOfFirstDay->id));
        $this->assertTrue($ids->contains($endOfLastDay->id));
        $this->assertFalse($ids->contains($beforeRange->id));
        $this->assertFalse($ids->contains($afterRange->id));
    }

    public function test_dashboard_date_range_filter_includes_the_full_first_and_last_day(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $endpoint = Endpoint::factory()->for($user)->create();
        $event = Event::factory()->for($user)->create();

        $startOfFirstDay = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-06-01 00:00:00',
        ]);
        $endOfLastDay = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-06-30 23:59:59',
        ]);
        $beforeRange = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-05-31 23:59:59',
        ]);
        $afterRange = Delivery::factory()->for($event)->for($endpoint)->create([
            'created_at' => '2026-07-01 00:00:00',
        ]);

        $response = $this->actingAs($user)->get(route('deliveries', [
            'from_date' => '2026-06-01',
            'to_date' => '2026-06-30',
        ]));

        $response->assertOk();
        $response->assertInertia(
            fn ($page) => $page
                ->component('Deliveries/Index')
                ->has('deliveries.data', 2)
                // Ordered by latest() first, so the end-of-range delivery sorts before the start-of-range one.
                ->where('deliveries.data.0.id', $endOfLastDay->id)
                ->where('deliveries.data.1.id', $startOfFirstDay->id)
        );
    }
}
