<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class EventEndpointPivotIndexTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Regression test for issue #113: the `event_endpoint` pivot only had a
     * composite unique index leading with `event_id`, which cannot serve a
     * lookup filtered by `endpoint_id` alone (as `Endpoint::events()` does)
     * under the leftmost-prefix rule for composite indexes.
     */
    public function test_event_endpoint_table_has_a_standalone_index_on_endpoint_id(): void
    {
        $indexes = Schema::getIndexes('event_endpoint');

        $hasStandaloneEndpointIdIndex = collect($indexes)->contains(
            fn (array $index) => $index['columns'] === ['endpoint_id']
        );

        $this->assertTrue(
            $hasStandaloneEndpointIdIndex,
            'Expected a standalone index on event_endpoint.endpoint_id.'
        );
    }
}
