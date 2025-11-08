<?php

namespace Tests\Feature\Tools\Booking;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Booking\PrepareBookingTool;
use App\Mcp\TourismServer\Tools\Booking\ConfirmBookingTool;

class ConfirmBookingToolTest extends TestCase
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

    public function test_confirm_booking_with_valid_booking_id(): void
    {
        // First prepare a booking
        $prepareResponse = TourismServer::tool(PrepareBookingTool::class, [
            'attraction_id' => 101,
            'number_of_tickets' => 2,
            'visit_date' => '2025-11-15',
            'visitor_name' => 'John Smith',
            'visitor_email' => 'john.smith@real-email.com',
            'card_number' => '4532015112830366',
            'card_holder_name' => 'John Smith',
            'card_expiry' => '12/26',
            'card_cvv' => '123',
        ]);

        // Extract booking ID from response
        $content = $this->extractContent($prepareResponse);

        preg_match('/BKG-[A-Z0-9]+/', $content, $matches);
        $bookingId = $matches[0] ?? null;

        $this->assertNotNull($bookingId);

        // Now confirm the booking
        $response = TourismServer::tool(ConfirmBookingTool::class, [
            'booking_id' => $bookingId,
            'payment_method' => 'credit_card',
        ]);

        $response
            ->assertOk()
            ->assertSee('Booking Confirmed')
            ->assertSee('CONFIRMED')
            ->assertSee('TXN-')
            ->assertSee('Ticket Numbers')
            ->assertSee('TKT-')
            ->assertSee('SUCCESSFUL');
    }

    public function test_confirm_booking_with_non_existent_booking_id(): void
    {
        $response = TourismServer::tool(ConfirmBookingTool::class, [
            'booking_id' => 'BKG-INVALID123',
        ]);

        $response
            ->assertOk()
            ->assertSee('not found');
    }

    public function test_confirm_already_confirmed_booking(): void
    {
        // First prepare a booking
        $prepareResponse = TourismServer::tool(PrepareBookingTool::class, [
            'attraction_id' => 101,
            'number_of_tickets' => 1,
            'visit_date' => '2025-11-15',
            'visitor_name' => 'Jane Doe',
            'visitor_email' => 'jane.doe@real-email.com',
            'card_number' => '4532015112830366',
            'card_holder_name' => 'Jane Doe',
            'card_expiry' => '12/26',
            'card_cvv' => '123',
        ]);

        $content = $this->extractContent($prepareResponse);
        preg_match('/BKG-[A-Z0-9]+/', $content, $matches);
        $bookingId = $matches[0] ?? null;

        // Confirm it once
        TourismServer::tool(ConfirmBookingTool::class, [
            'booking_id' => $bookingId,
        ]);

        // Try to confirm again
        $response = TourismServer::tool(ConfirmBookingTool::class, [
            'booking_id' => $bookingId,
        ]);

        $response
            ->assertOk()
            ->assertSee('already been confirmed');
    }
}

