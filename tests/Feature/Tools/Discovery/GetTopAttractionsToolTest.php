<?php

namespace Tests\Feature\Tools\Discovery;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Discovery\GetTopAttractionsTool;

class GetTopAttractionsToolTest extends TestCase
{
    public function test_get_top_attractions_with_destination_name(): void
    {
        $response = TourismServer::tool(GetTopAttractionsTool::class, [
            'destination_name' => 'Vienna',
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('Top')
            ->assertSee('Attractions in Vienna')
            ->assertSee('Palace');
    }

    public function test_get_top_attractions_with_destination_id(): void
    {
        $response = TourismServer::tool(GetTopAttractionsTool::class, [
            'destination_id' => 1,
            'limit' => 3,
        ]);

        $response
            ->assertOk()
            ->assertSee('Top')
            ->assertSee('Attractions');
    }

    public function test_get_top_attractions_without_destination_returns_error(): void
    {
        $response = TourismServer::tool(GetTopAttractionsTool::class, [
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('provide either a destination_name or destination_id');
    }

    public function test_get_top_attractions_with_non_existent_destination(): void
    {
        $response = TourismServer::tool(GetTopAttractionsTool::class, [
            'destination_name' => 'NonExistentCity',
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('not found');
    }
}

