<?php

namespace App\Mcp\CapCornServer\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class SearchRoomAvailabilityTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Search room availability for specific check-in and check-out dates.
        Direct search when you know exact arrival and departure dates.
        Supports searching for multiple rooms in one request (max 10 rooms).
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'language' => 'nullable|integer|in:0,1',
            'hotel_id' => 'required|string',
            'arrival' => 'required|date_format:Y-m-d',
            'departure' => 'required|date_format:Y-m-d|after:arrival',
            'rooms' => 'required|array|min:1|max:10',
            'rooms.*.adults' => 'required|integer|min:1',
            'rooms.*.children' => 'nullable|array|max:8',
            'rooms.*.children.*.age' => 'required|integer|min:1|max:17',
        ]);

        $baseUrl = config('services.capcorn.base_url');

        $requestBody = [
            'language' => $validated['language'] ?? 0,
            'hotel_id' => $validated['hotel_id'],
            'arrival' => $validated['arrival'],
            'departure' => $validated['departure'],
            'rooms' => $validated['rooms'],
        ];

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($baseUrl . '/api/v1/rooms/availability', $requestBody);

            if (!$response->successful()) {
                $error = $response->json();
                return Response::text('Availability search failed: ' . ($error['detail'] ?? $response->status()));
            }

            $data = $response->json();
            
            return Response::text($this->formatAvailability($data, $validated));
        } catch (\Throwable $e) {
            Log::error('SearchRoomAvailabilityTool error', ['error' => $e->getMessage()]);
            return Response::text('Failed to search availability. Please try again later.');
        }
    }

    private function formatAvailability(array $data, array $request): string
    {
        $members = $data['members'] ?? [];
        
        if (empty($members)) {
            return "No availability found for the specified dates.";
        }

        $formatted = "🏨 Room Availability\n";
        $formatted .= "📅 {$request['arrival']} → {$request['departure']}\n";
        $formatted .= "👥 " . count($request['rooms']) . " room(s) requested\n\n";

        foreach ($members as $memberIndex => $member) {
            $hotelId = $member['hotel_id'] ?? 'Unknown';
            $rooms = $member['rooms'] ?? [];

            $formatted .= "🏢 Hotel ID: {$hotelId}\n";
            $formatted .= str_repeat('═', 60) . "\n\n";

            foreach ($rooms as $roomIndex => $roomSearch) {
                $roomNum = $roomIndex + 1;
                $adults = $roomSearch['adults'] ?? 0;
                $children = $roomSearch['children'] ?? [];
                $options = $roomSearch['options'] ?? [];

                $formatted .= "**Room {$roomNum} Request:**\n";
                $formatted .= "👤 Adults: {$adults}";
                
                if (!empty($children)) {
                    $ages = array_column($children, 'age');
                    $formatted .= " | 👶 Children: " . count($children) . " (ages: " . implode(', ', $ages) . ")";
                }
                $formatted .= "\n\n";

                if (empty($options)) {
                    $formatted .= "❌ No rooms available for this configuration.\n\n";
                    continue;
                }

                $formatted .= "Available Options:\n";
                $formatted .= str_repeat('─', 60) . "\n";

                foreach ($options as $optIndex => $option) {
                    $optNum = $optIndex + 1;
                    $mealPlan = $this->getMealPlanName($option['board']);
                    $roomTypeName = $option['room_type'] == 1 ? 'Hotel Room' : 'Apartment';

                    $formatted .= "\n{$optNum}. **{$option['type']}** ({$roomTypeName})\n";
                    $formatted .= "   Code: {$option['catc']}\n";
                    $formatted .= "   {$option['description']}\n";
                    $formatted .= "   Size: {$option['size']} m²\n";
                    $formatted .= "   Meal Plan: {$mealPlan}\n\n";
                    
                    $formatted .= "   💰 Pricing:\n";
                    $formatted .= "   • Total: €" . number_format($option['price'], 2) . "\n";
                    $formatted .= "   • Per night: €" . number_format($option['price_per_night'], 2) . "\n";
                    $formatted .= "   • Per person: €" . number_format($option['price_per_person'], 2) . "\n";
                    $formatted .= "   • Per adult: €" . number_format($option['price_per_adult'], 2) . "\n\n";
                }
            }

            $formatted .= "\n";
        }

        $formatted .= "💡 Use CreateReservation with the room code (catc) to book.";

        return $formatted;
    }

    private function getMealPlanName(int $code): string
    {
        return match($code) {
            1 => 'Breakfast',
            2 => 'Half Board (Breakfast + Dinner)',
            3 => 'Full Board (All Meals)',
            4 => 'No Meals',
            5 => 'All Inclusive',
            default => "Code {$code}",
        };
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->integer()->description('Language: 0 for German, 1 for English (default: 0).'),
            'hotel_id' => $schema->string()->description('CapCorn Hotel ID.'),
            'arrival' => $schema->string()->description('Check-in date (YYYY-MM-DD).'),
            'departure' => $schema->string()->description('Check-out date (YYYY-MM-DD).'),
            'rooms' => $schema->array()->items(
                $schema->object([
                    'adults' => $schema->integer()->description('Number of adults (minimum 1).'),
                    'children' => $schema->array()->items(
                        $schema->object([
                            'age' => $schema->integer()->description('Child age 1-17.'),
                        ])
                    )->description('List of children with ages (max 8).'),
                ])
            )->description('Room configurations to search (max 10 rooms).'),
        ];
    }
}
