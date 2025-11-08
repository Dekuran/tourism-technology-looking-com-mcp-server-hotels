<?php

namespace Tests\Feature\Tools\Discovery;

use Tests\TestCase;
use App\Mcp\TourismServer\TourismServer;
use App\Mcp\TourismServer\Tools\Discovery\RecommendAttractionsTool;

class RecommendAttractionsToolTest extends TestCase
{
    public function test_recommend_attractions_with_preferences(): void
    {
        $response = TourismServer::tool(RecommendAttractionsTool::class, [
            'destination_name' => 'Vienna',
            'preferences' => ['art', 'history'],
            'travel_type' => 'cultural',
            'age_group' => 'adult',
            'budget' => 'moderate',
            'limit' => 6,
        ]);

        $response
            ->assertOk()
            ->assertSee('Personalized Recommendations')
            ->assertSee('Vienna')
            ->assertSee('Your Interests')
            ->assertSee('Match Score');
    }

    public function test_recommend_attractions_with_destination_id(): void
    {
        $response = TourismServer::tool(RecommendAttractionsTool::class, [
            'destination_id' => 1,
            'preferences' => ['nature', 'adventure'],
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('Personalized Recommendations')
            ->assertSee('attractions');
    }

    public function test_recommend_attractions_without_destination_returns_error(): void
    {
        $response = TourismServer::tool(RecommendAttractionsTool::class, [
            'preferences' => ['art'],
            'limit' => 4,
        ]);

        $response
            ->assertOk()
            ->assertSee('Destination not found');
    }

    public function test_recommend_attractions_with_user_id_tracking(): void
    {
        $response = TourismServer::tool(RecommendAttractionsTool::class, [
            'user_id' => 'USR-TEST-001',
            'destination_name' => 'Salzburg',
            'preferences' => ['music', 'culture'],
            'travel_type' => 'solo',
            'limit' => 5,
        ]);

        $response
            ->assertOk()
            ->assertSee('Personalized Recommendations')
            ->assertSee('Salzburg');
    }
}

