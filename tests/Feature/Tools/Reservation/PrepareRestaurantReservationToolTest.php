<?php

namespace Tests\Feature\Tools\Reservation;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Reservation\PrepareRestaurantReservationTool;

class PrepareRestaurantReservationToolTest extends TestCase
{
    public function test_prepare_restaurant_reservation_with_valid_data(): void
    {
        $response = TourismServer::tool(PrepareRestaurantReservationTool::class, [
            'attraction_id' => 501,
            'number_of_people' => 4,
            'reservation_date' => '2025-11-15',
            'reservation_time' => '7:00 PM',
            'guest_name' => 'Sarah Johnson',
            'guest_email' => 'sarah.johnson@real-email.com',
            'special_requests' => 'Window seat please',
        ]);

        $response
            ->assertOk()
            ->assertSee('Restaurant Reservation Prepared')
            ->assertSee('RSV-')
            ->assertSee('Cafe Schwarzenberg')
            ->assertSee('Sarah Johnson')
            ->assertSee('PENDING')
            ->assertSee('No payment required');
    }

    public function test_prepare_restaurant_reservation_without_special_requests(): void
    {
        $response = TourismServer::tool(PrepareRestaurantReservationTool::class, [
            'attraction_id' => 501,
            'number_of_people' => 2,
            'reservation_date' => '2025-11-15',
            'reservation_time' => '12:30 PM',
            'guest_name' => 'Michael Brown',
            'guest_email' => 'michael.brown@real-email.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('Restaurant Reservation Prepared')
            ->assertSee('RSV-')
            ->assertSee('Michael Brown');
    }

    public function test_prepare_restaurant_reservation_for_non_restaurant_attraction(): void
    {
        $response = TourismServer::tool(PrepareRestaurantReservationTool::class, [
            'attraction_id' => 101,
            'number_of_people' => 2,
            'reservation_date' => '2025-11-15',
            'reservation_time' => '7:00 PM',
            'guest_name' => 'Test User',
            'guest_email' => 'test@real-email.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('not a restaurant or cafe');
    }

    public function test_prepare_restaurant_reservation_for_non_existent_attraction(): void
    {
        $response = TourismServer::tool(PrepareRestaurantReservationTool::class, [
            'attraction_id' => 99999,
            'number_of_people' => 2,
            'reservation_date' => '2025-11-15',
            'reservation_time' => '7:00 PM',
            'guest_name' => 'Test User',
            'guest_email' => 'test@real-email.com',
        ]);

        $response
            ->assertOk()
            ->assertSee('not found');
    }
}

