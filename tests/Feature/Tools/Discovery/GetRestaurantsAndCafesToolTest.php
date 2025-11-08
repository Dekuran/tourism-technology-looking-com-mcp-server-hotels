<?php

namespace Tests\Feature\Tools\Discovery;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Discovery\GetRestaurantsAndCafesTool;

class GetRestaurantsAndCafesToolTest extends TestCase
{
    public function test_get_restaurants_and_cafes_with_destination_name(): void
    {
        $response = TourismServer::tool(GetRestaurantsAndCafesTool::class, [
            'destination_name' => 'Vienna',
            'limit' => 6,
        ]);

        $response
            ->assertOk()
            ->assertSee('Restaurants & Cafes')
            ->assertSee('Vienna')
            ->assertSee('dining');
    }

    public function test_get_restaurants_and_cafes_with_destination_id(): void
    {
        $response = TourismServer::tool(GetRestaurantsAndCafesTool::class, [
            'destination_id' => 1,
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('Restaurants & Cafes');
    }

    public function test_get_restaurants_and_cafes_without_destination_returns_error(): void
    {
        $response = TourismServer::tool(GetRestaurantsAndCafesTool::class, [
            'limit' => 6,
        ]);

        $response
            ->assertOk()
            ->assertSee('provide either');
    }
}

