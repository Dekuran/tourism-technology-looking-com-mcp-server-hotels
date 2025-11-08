<?php

namespace App\Mcp\CapCornServer\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class SearchRoomsTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Search for available rooms with flexible duration within a timespan.
        Generates all possible date ranges for the specified duration and searches them in parallel.
        Perfect for finding rooms when you have flexible dates within a period.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'language' => 'nullable|string|in:de,en',
            'timespan' => 'required|array',
            'timespan.from' => 'required|date_format:Y-m-d',
            'timespan.to' => 'required|date_format:Y-m-d|after:timespan.from',
            'duration' => 'required|integer|min:1',
            'adults' => 'required|integer|min:1',
            'children' => 'nullable|array|max:8',
            'children.*.age' => 'required|integer|min:1|max:17',
        ]);

        $baseUrl = config('services.capcorn.base_url');

        $requestBody = [
            'language' => $validated['language'] ?? 'de',
            'timespan' => $validated['timespan'],
            'duration' => $validated['duration'],
            'adults' => $validated['adults'],
        ];

        if (!empty($validated['children'])) {
            $requestBody['children'] = $validated['children'];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($baseUrl . '/api/v1/rooms/search', $requestBody);

            if (!$response->successful()) {
                $error = $response->json();
                return Response::text('Room search failed: ' . ($error['detail'] ?? $response->status()));
            }

            $data = $response->json();
            
            return Response::text($this->formatSearchResults($data));
        } catch (\Throwable $e) {
            Log::error('SearchRoomsTool error', ['error' => $e->getMessage()]);
            return Response::text('Failed to search rooms. Please try again later.');
        }
    }

    private function formatSearchResults(array $data): string
    {
        $totalQueries = $data['total_queries'] ?? 0;
        $totalOptions = $data['total_options'] ?? 0;
        $duration = $data['duration_days'] ?? 0;
        $options = $data['options'] ?? [];

        if (empty($options)) {
            return "No rooms available for {$duration}-night stays in the specified timespan.";
        }

        $formatted = "🏨 Found {$totalOptions} room options for {$duration}-night stays\n";
        $formatted .= "Searched {$totalQueries} different date combinations\n\n";

        // Group by date range
        $byDateRange = [];
        foreach ($options as $option) {
            $key = $option['arrival'] . ' → ' . $option['departure'];
            if (!isset($byDateRange[$key])) {
                $byDateRange[$key] = [];
            }
            $byDateRange[$key][] = $option;
        }

        foreach ($byDateRange as $dateRange => $rooms) {
            $formatted .= "📅 **{$dateRange}**\n";
            $formatted .= str_repeat('─', 50) . "\n\n";

            foreach ($rooms as $index => $room) {
                $num = $index + 1;
                $mealPlan = $this->getMealPlanName($room['board']);
                $roomTypeName = $room['room_type'] == 1 ? 'Hotel Room' : 'Apartment';

                $formatted .= "**{$num}. {$room['type']}** ({$roomTypeName})\n";
                $formatted .= "   Code: {$room['catc']}\n";
                $formatted .= "   {$room['description']}\n";
                $formatted .= "   Size: {$room['size']} m²\n";
                $formatted .= "   Meal Plan: {$mealPlan}\n\n";
                
                $formatted .= "   💰 Pricing:\n";
                $formatted .= "   • Total: €" . number_format($room['price'], 2) . "\n";
                $formatted .= "   • Per night: €" . number_format($room['price_per_night'], 2) . "\n";
                $formatted .= "   • Per person: €" . number_format($room['price_per_person'], 2) . "\n";
                $formatted .= "   • Per adult: €" . number_format($room['price_per_adult'], 2) . "\n\n";
            }
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
            'language' => $schema->string()->description('Language: "de" for German, "en" for English (default: "de").'),
            'timespan' => $schema->object([
                'from' => $schema->string()->description('Start date of search period (YYYY-MM-DD).'),
                'to' => $schema->string()->description('End date of search period (YYYY-MM-DD).'),
            ])->description('Date range to search within.'),
            'duration' => $schema->integer()->description('Length of stay in days (must be ≤ timespan).'),
            'adults' => $schema->integer()->description('Number of adults (minimum 1).'),
            'children' => $schema->array()->items(
                $schema->object([
                    'age' => $schema->integer()->description('Child age 1-17.'),
                ])
            )->description('List of children with ages (max 8).'),
        ];
    }
}
