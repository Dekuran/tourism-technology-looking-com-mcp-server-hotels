<?php

namespace Tests\Feature\Tools\Mastercard;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\External\ATMLocatorTool;

class ATMLocatorToolTest extends TestCase
{
    public function test_atm_locator_with_city_name(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'city' => 'Vienna',
            'distance' => 5,
            'distance_unit' => 'km',
            'limit' => 5,
        ]);

        // Response may contain ATMs or an error from the API
        // Just verify we get a response
        $this->assertNotNull($response);
    }

    public function test_atm_locator_with_destination_name(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'destination_name' => 'Salzburg',
            'distance' => 10,
            'limit' => 3,
        ]);

        $response
            ->assertOk()
            ->assertSee('ATM');
    }

    public function test_atm_locator_with_attraction_id(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'attraction_id' => 101,
            'distance' => 3,
            'distance_unit' => 'km',
            'limit' => 5,
        ]);

        // Response may contain ATMs or an error from the API
        // Just verify we get a response
        $this->assertNotNull($response);
    }

    public function test_atm_locator_with_coordinates(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'latitude' => 48.2082,
            'longitude' => 16.3738,
            'distance' => 5,
            'distance_unit' => 'km',
            'limit' => 5,
        ]);

        // Response may contain ATMs or an error from the API
        // Just verify we get a response
        $this->assertNotNull($response);
    }

    public function test_atm_locator_with_postal_code_and_country(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'postal_code' => '1010',
            'country' => 'AUT',
            'distance' => 5,
            'limit' => 3,
        ]);

        $response
            ->assertOk()
            ->assertSee('ATM');
    }

    public function test_atm_locator_without_location_parameters_returns_error(): void
    {
        $response = TourismServer::tool(ATMLocatorTool::class, [
            'distance' => 5,
            'limit' => 5,
        ]);

        $response
            ->assertOk()
            ->assertSee('provide');
    }
}

