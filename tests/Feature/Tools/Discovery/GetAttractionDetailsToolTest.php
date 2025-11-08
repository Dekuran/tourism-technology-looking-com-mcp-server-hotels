<?php

namespace Tests\Feature\Tools\Discovery;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Discovery\GetAttractionDetailsTool;

class GetAttractionDetailsToolTest extends TestCase
{
    public function test_get_attraction_details_with_valid_id(): void
    {
        $response = TourismServer::tool(GetAttractionDetailsTool::class, [
            'attraction_id' => 101,
        ]);

        $response
            ->assertOk()
            ->assertSee('Palace')
            ->assertSee('Category')
            ->assertSee('Description')
            ->assertSee('Booking Information');
    }

    public function test_get_attraction_details_with_non_existent_id(): void
    {
        $response = TourismServer::tool(GetAttractionDetailsTool::class, [
            'attraction_id' => 99999,
        ]);

        $response
            ->assertOk()
            ->assertSee('not found');
    }
}

