<?php

namespace App\Mcp\CapCornServer\Tools;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class CreateReservationTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a new hotel reservation with guest information and optional services.
        Requires complete guest details including name, contact information, and address.
        Returns success confirmation or error details.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'hotel_id' => 'required|string',
            'room_type_code' => 'required|string|max:8',
            'number_of_units' => 'nullable|integer|min:1',
            'meal_plan' => 'required|integer|in:1,2,3,4,5',
            'guest_counts' => 'required|array|min:1',
            'guest_counts.*.age_qualifying_code' => 'required|integer|in:8,10',
            'guest_counts.*.count' => 'required|integer|min:1',
            'guest_counts.*.age' => 'nullable|integer|min:1|max:17',
            'arrival' => 'required|date_format:Y-m-d',
            'departure' => 'required|date_format:Y-m-d|after:arrival',
            'total_amount' => 'required|numeric|min:0',
            'guest' => 'required|array',
            'guest.name_prefix' => 'required|string',
            'guest.given_name' => 'required|string',
            'guest.surname' => 'required|string',
            'guest.phone_number' => 'required|string',
            'guest.email' => 'required|email',
            'guest.address' => 'required|array',
            'guest.address.address_line' => 'required|string',
            'guest.address.city_name' => 'required|string',
            'guest.address.postal_code' => 'required|string',
            'guest.address.country_code' => 'required|string|size:2',
            'services' => 'nullable|array',
            'services.*.name' => 'required_with:services|string',
            'services.*.quantity' => 'required_with:services|integer|min:1',
            'services.*.amount_after_tax' => 'required_with:services|numeric|min:0',
            'booking_comment' => 'nullable|string|max:200',
            'reservation_id' => 'required|string',
            'source' => 'nullable|string',
        ]);

        $baseUrl = config('services.capcorn.base_url');

        $requestBody = [
            'hotel_id' => $validated['hotel_id'],
            'room_type_code' => $validated['room_type_code'],
            'number_of_units' => $validated['number_of_units'] ?? 1,
            'meal_plan' => $validated['meal_plan'],
            'guest_counts' => $validated['guest_counts'],
            'arrival' => $validated['arrival'],
            'departure' => $validated['departure'],
            'total_amount' => $validated['total_amount'],
            'guest' => $validated['guest'],
            'reservation_id' => $validated['reservation_id'],
            'source' => $validated['source'] ?? 'Hackathon',
        ];

        if (!empty($validated['services'])) {
            $requestBody['services'] = $validated['services'];
        }

        if (!empty($validated['booking_comment'])) {
            $requestBody['booking_comment'] = $validated['booking_comment'];
        }

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($baseUrl . '/api/v1/reservations', $requestBody);

            if (!$response->successful()) {
                $error = $response->json();
                return Response::text('Reservation failed: ' . json_encode($error['detail'] ?? $response->body()));
            }

            $data = $response->json();
            
            return Response::text($this->formatReservationResponse($data, $validated));
        } catch (\Throwable $e) {
            Log::error('CreateReservationTool error', ['error' => $e->getMessage()]);
            return Response::text('Failed to create reservation. Please try again later.');
        }
    }

    private function formatReservationResponse(array $data, array $request): string
    {
        $success = $data['success'] ?? false;
        $message = $data['message'] ?? 'Unknown status';
        $reservationId = $data['reservation_id'] ?? null;
        $errors = $data['errors'] ?? [];

        if (!$success) {
            $formatted = "❌ Reservation Failed\n\n";
            $formatted .= "Message: {$message}\n";
            
            if (!empty($errors)) {
                $formatted .= "\nErrors:\n";
                foreach ($errors as $error) {
                    $formatted .= "• {$error}\n";
                }
            }
            
            return $formatted;
        }

        $formatted = "✅ Reservation Confirmed!\n\n";
        $formatted .= "🎫 Reservation ID: {$reservationId}\n";
        $formatted .= "🏨 Hotel: {$request['hotel_id']}\n";
        $formatted .= "🛏️ Room: {$request['room_type_code']}\n";
        $formatted .= "📅 Check-in: {$request['arrival']}\n";
        $formatted .= "📅 Check-out: {$request['departure']}\n";
        $formatted .= "💰 Total: €" . number_format($request['total_amount'], 2) . "\n\n";

        $formatted .= "👤 Guest: {$request['guest']['name_prefix']} {$request['guest']['given_name']} {$request['guest']['surname']}\n";
        $formatted .= "📧 Email: {$request['guest']['email']}\n";
        $formatted .= "📞 Phone: {$request['guest']['phone_number']}\n\n";

        $mealPlan = $this->getMealPlanName($request['meal_plan']);
        $formatted .= "🍽️ Meal Plan: {$mealPlan}\n";

        if (!empty($request['services'])) {
            $formatted .= "\n🎁 Additional Services:\n";
            foreach ($request['services'] as $service) {
                $formatted .= "• {$service['name']} (×{$service['quantity']}) - €" . number_format($service['amount_after_tax'], 2) . "\n";
            }
        }

        if (!empty($request['booking_comment'])) {
            $formatted .= "\n💬 Comment: {$request['booking_comment']}\n";
        }

        $formatted .= "\n📝 Message: {$message}";

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
            'hotel_id' => $schema->string()->description('CapCorn Hotel ID.'),
            'room_type_code' => $schema->string()->description('Room category code from availability search (max 8 characters).'),
            'number_of_units' => $schema->integer()->description('Number of rooms to book (default: 1).'),
            'meal_plan' => $schema->integer()->description('Meal plan: 1=Breakfast, 2=Half board, 3=Full board, 4=No meals, 5=All inclusive.'),
            'guest_counts' => $schema->array()->items(
                $schema->object([
                    'age_qualifying_code' => $schema->integer()->description('10=Adults, 8=Children.'),
                    'count' => $schema->integer()->description('Number of guests.'),
                    'age' => $schema->integer()->description('Required for children (1-17).'),
                ])
            )->description('Guest counts by type.'),
            'arrival' => $schema->string()->description('Check-in date (YYYY-MM-DD).'),
            'departure' => $schema->string()->description('Check-out date (YYYY-MM-DD).'),
            'total_amount' => $schema->number()->description('Total price in EUR.'),
            'guest' => $schema->object([
                'name_prefix' => $schema->string()->description('Name prefix (e.g., Herr, Frau, Mr, Ms).'),
                'given_name' => $schema->string()->description('First name.'),
                'surname' => $schema->string()->description('Last name.'),
                'phone_number' => $schema->string()->description('Phone number.'),
                'email' => $schema->string()->description('Email address.'),
                'address' => $schema->object([
                    'address_line' => $schema->string()->description('Street address.'),
                    'city_name' => $schema->string()->description('City.'),
                    'postal_code' => $schema->string()->description('Postal/ZIP code.'),
                    'country_code' => $schema->string()->description('2-letter country code (e.g., DE, AT, US).'),
                ])->description('Guest address.'),
            ])->description('Guest personal information.'),
            'services' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->description('Service name.'),
                    'quantity' => $schema->integer()->description('Quantity.'),
                    'amount_after_tax' => $schema->number()->description('Price after tax per unit.'),
                ])
            )->description('Optional additional services.'),
            'booking_comment' => $schema->string()->description('Special requests or comments (max 200 characters).'),
            'reservation_id' => $schema->string()->description('Unique booking ID from your system.'),
            'source' => $schema->string()->description('Source/channel name (default: "Hackathon").'),
        ];
    }
}
