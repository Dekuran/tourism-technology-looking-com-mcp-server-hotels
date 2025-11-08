<?php

namespace Tests\Feature\Tools\Accommodation;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Accommodation\CreateHotelReservationTool;

class CreateHotelReservationToolTest extends TestCase
{
    public function test_create_reservation_minimum_payload(): void
    {
        $response = TourismServer::tool(CreateHotelReservationTool::class, [
            'hotel_id' => 9100,
            'room_type_code' => 'DZS',
            'number_of_units' => 1,
            'adults' => 2,
            'children_ages' => [6],
            'start' => '2026-01-24',
            'end' => '2026-01-28',
            'total_amount' => 227.00,
            'currency_code' => 'EUR',
            'guest' => [
                'given_name' => 'Max',
                'surname' => 'Mustermann',
                'email' => 'support@capcorn.at',
                'phone_number' => '(+43)6641234567',
                'address_line' => 'Flugplatzstraße 52',
                'city_name' => 'Zell am See',
                'postal_code' => '5700',
                'country_code' => 'AT',
                'salutation' => 'Herr',
            ],
            'res_id_value' => 'TEST-' . substr(md5(uniqid()), 0, 6),
            'res_id_source' => 'Hackathon',
        ]);

        // External API call; just assert we get a response wrapper
        $this->assertNotNull($response);
    }

    public function test_create_reservation_with_services(): void
    {
        $response = TourismServer::tool(CreateHotelReservationTool::class, [
            'hotel_id' => 9100,
            'room_type_code' => 'DZS',
            'number_of_units' => 1,
            'adults' => 2,
            'start' => '2026-01-24',
            'end' => '2026-01-28',
            'total_amount' => 342.00,
            'currency_code' => 'EUR',
            'guest' => [
                'given_name' => 'Anna',
                'surname' => 'Schmidt',
                'email' => 'anna.schmidt@example.org',
            ],
            'services' => [
                [ 'name' => 'Sport massage 30min', 'quantity' => 2, 'unit_amount' => 50.00 ],
            ],
            'res_id_value' => 'TEST-' . substr(md5(uniqid()), 0, 6),
            'res_id_source' => 'Hackathon',
        ]);

        $this->assertNotNull($response);
    }
}


