<?php

namespace Tests\Feature\Tools\Reservation;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Reservation\PrepareRestaurantReservationTool;
use App\Mcp\TourismServer\Tools\Reservation\ConfirmRestaurantReservationTool;

class ConfirmRestaurantReservationToolTest extends TestCase
{
    /**
     * Extract content from MCP test response using reflection
     */
    private function extractContent($response): string
    {
        $reflection = new \ReflectionClass($response);
        $method = $reflection->getMethod('content');
        $method->setAccessible(true);
        $contentArray = $method->invoke($response);
        return implode("\n", $contentArray);
    }

    public function test_confirm_restaurant_reservation_with_valid_reservation_id(): void
    {
        // First prepare a reservation
        $prepareResponse = TourismServer::tool(PrepareRestaurantReservationTool::class, [
            'attraction_id' => 501,
            'number_of_people' => 3,
            'reservation_date' => '2025-11-15',
            'reservation_time' => '6:30 PM',
            'guest_name' => 'Emma Wilson',
            'guest_email' => 'emma.wilson@real-email.com',
            'special_requests' => 'Birthday celebration',
        ]);

        // Extract reservation ID from response
        $content = $this->extractContent($prepareResponse);
        preg_match('/RSV-[A-Z0-9]+/', $content, $matches);
        $reservationId = $matches[0] ?? null;

        $this->assertNotNull($reservationId);

        // Now confirm the reservation
        $response = TourismServer::tool(ConfirmRestaurantReservationTool::class, [
            'reservation_id' => $reservationId,
        ]);

        $response
            ->assertOk()
            ->assertSee('Restaurant Reservation Confirmed')
            ->assertSee('CONFIRMED')
            ->assertSee('CNF-')
            ->assertSee('Emma Wilson')
            ->assertSee('Birthday celebration')
            ->assertSee('No payment was processed');
    }

    public function test_confirm_restaurant_reservation_with_non_existent_reservation_id(): void
    {
        $response = TourismServer::tool(ConfirmRestaurantReservationTool::class, [
            'reservation_id' => 'RSV-INVALID123',
        ]);

        $response
            ->assertOk()
            ->assertSee('Reservation not found');
    }
}

