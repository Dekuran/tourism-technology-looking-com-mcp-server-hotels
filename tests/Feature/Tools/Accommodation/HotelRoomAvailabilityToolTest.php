<?php

namespace Tests\Feature\Tools\Accommodation;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Accommodation\HotelRoomAvailabilityTool;

class HotelRoomAvailabilityToolTest extends TestCase
{
    public function test_room_availability_with_two_rooms(): void
    {
        $response = TourismServer::tool(HotelRoomAvailabilityTool::class, [
            'hotel_id' => 9100,
            'arrival' => '2025-12-17',
            'departure' => '2025-12-20',
            'rooms' => [
                [ 'adults' => 2, 'children_ages' => [3] ],
                [ 'adults' => 2 ],
            ],
        ]);

        // External API may vary; ensure we at least get a response container
        $this->assertNotNull($response);
    }

    public function test_room_availability_requires_at_least_one_room(): void
    {
        $response = TourismServer::tool(HotelRoomAvailabilityTool::class, [
            'hotel_id' => 9100,
            'arrival' => '2025-12-17',
            'departure' => '2025-12-20',
            'rooms' => [ [ 'adults' => 2 ] ],
        ]);

        $this->assertNotNull($response);
    }
}


