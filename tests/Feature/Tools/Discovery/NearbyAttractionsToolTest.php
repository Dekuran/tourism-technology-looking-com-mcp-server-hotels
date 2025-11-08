<?php

namespace Tests\Feature\Tools\Discovery;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Discovery\NearbyAttractionsTool;

class NearbyAttractionsToolTest extends TestCase
{
    public function test_nearby_attractions_with_destination_name(): void
    {
        $response = TourismServer::tool(NearbyAttractionsTool::class, [
            'destination_name' => 'Vienna',
            'radius_km' => 10,
        ]);

        $response
            ->assertOk()
            ->assertSee('nearby attractions')
            ->assertSee('Distance');
    }

    public function test_nearby_attractions_with_coordinates(): void
    {
        $response = TourismServer::tool(NearbyAttractionsTool::class, [
            'latitude' => 48.2082,
            'longitude' => 16.3738,
            'radius_km' => 5,
        ]);

        $response
            ->assertOk()
            ->assertSee('nearby attractions');
    }

    public function test_nearby_attractions_with_non_existent_destination(): void
    {
        $response = TourismServer::tool(NearbyAttractionsTool::class, [
            'destination_name' => 'UnknownPlace',
            'radius_km' => 10,
        ]);

        $response
            ->assertOk()
            ->assertSee('not found');
    }
}

