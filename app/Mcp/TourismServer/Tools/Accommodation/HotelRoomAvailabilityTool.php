<?php

namespace App\Mcp\TourismServer\Tools\Accommodation;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class HotelRoomAvailabilityTool extends Tool
{
    /**
     * The tool's description.
     */
    protected string $description = <<<'MARKDOWN'
        Search hotel room availability for a given date range and occupancy.
        Uses a hotel availability service to return bookable room options with pricing and board info.
    MARKDOWN;

    /**
     * Handle the tool request.
     */
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'hotel_id' => 'nullable|integer',
            'arrival' => 'required|date_format:Y-m-d',
            'departure' => 'required|date_format:Y-m-d|after:arrival',
            'rooms' => 'required|array|min:1|max:10',
            'rooms.*.adults' => 'required|integer|min:1|max:15',
            'rooms.*.children_ages' => 'nullable|array|max:8',
            'rooms.*.children_ages.*' => 'integer|min:1|max:17'
        ]);

        $endpoint = config('services.hotel_availability.endpoint');
        $system = config('services.hotel_availability.system');
        $user = config('services.hotel_availability.user');
        $password = config('services.hotel_availability.password');
        $hotelId = $validated['hotel_id'] ?? config('services.hotel_availability.default_hotel_id');
        $language = config('services.hotel_availability.default_language'); 

        $url = $endpoint . '?user=' . urlencode($user) . '&password=' . urlencode($password) . '&system=' . urlencode($system);

        // Build XML request body
        $xml = $this->buildRequestXml([...$validated, 'language' => $language, 'hotel_id' => $hotelId]);

        try {
            $response = Http::withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/xml',
                ])
                ->withBody($xml, 'application/xml')
                ->send('POST', $url);

            if (!$response->ok()) {
                return Response::text("Room availability service error: HTTP " . $response->status());
            }

            $parsed = $this->parseResponseXml($response->body());
            if (empty($parsed['rooms'])) {
                return Response::text("No rooms available for the selected dates and occupancy.");
            }

            $markdown = $this->formatMarkdownResult(
                hotelId: (int) $hotelId,
                arrival: $validated['arrival'],
                departure: $validated['departure'],
                language: $language,
                rooms: $parsed['rooms']
            );

            return Response::text($markdown);
        } catch (\Throwable $e) {
            Log::error('HotelRoomAvailabilityTool error', ['error' => $e->getMessage()]);
            return Response::text('Failed to query room availability. Please try again later.');
        }
    }

    /**
     * Build XML payload for room availability request.
     */
    private function buildRequestXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<room_availability/>');

        $xml->addChild('language', $data['language'] ?? 0);

        $members = $xml->addChild('members');
        $member = $members->addChild('member');
        $member->addAttribute('hotel_id', $data['hotel_id']);

        $xml->addChild('arrival', $data['arrival']);
        $xml->addChild('departure', $data['departure']);

        $rooms = $xml->addChild('rooms');

        foreach ($data['rooms'] as $roomData) {
            $room = $rooms->addChild('room');
            $room->addAttribute('adults', $roomData['adults']);

            if (!empty($roomData['children'])) {
                foreach ($roomData['children'] as $age) {
                    $child = $room->addChild('child');
                    $child->addAttribute('age', $age);
                }
            }
        }

        return $xml->asXML();
    }

    /**
     * Parse XML response into a simplified array.
     */
    private function parseResponseXml(string $xml): array
    {
        // Suppress errors and handle parsing failures gracefully
        $prev = libxml_use_internal_errors(true);
        $simple = simplexml_load_string($xml);
        libxml_use_internal_errors($prev);

        if ($simple === false) {
            return ['rooms' => []];
        }

        // Response uses a default namespace; register a prefix for XPath
        $namespaces = $simple->getNamespaces(true);
        $defaultNamespace = $namespaces[''] ?? (count($namespaces) ? reset($namespaces) : null);
        if ($defaultNamespace) {
            $simple->registerXPathNamespace('ns', $defaultNamespace);
            $roomNodes = $simple->xpath('//ns:room') ?: [];
        } else {
            $roomNodes = $simple->xpath('//room') ?: [];
        }

        $rooms = [];
        foreach ($roomNodes as $roomNode) {
            if ($defaultNamespace) {
                // Register on the node as well before invoking xpath
                $roomNode->registerXPathNamespace('ns', $defaultNamespace);
                $arrivalNode = $roomNode->xpath('ns:arrival');
                $departureNode = $roomNode->xpath('ns:departure');
                $adultsNode = $roomNode->xpath('ns:adults');
                $arrival = isset($arrivalNode[0]) ? (string) $arrivalNode[0] : '';
                $departure = isset($departureNode[0]) ? (string) $departureNode[0] : '';
                $adults = isset($adultsNode[0]) ? (string) $adultsNode[0] : '';
            } else {
                $arrival = (string) ($roomNode->arrival ?? '');
                $departure = (string) ($roomNode->departure ?? '');
                $adults = (string) ($roomNode->adults ?? '');
            }

            // Options
            if ($defaultNamespace) {
                $optionNodes = $roomNode->xpath('ns:options/ns:option') ?: [];
            } else {
                $optionNodes = $roomNode->xpath('options/option') ?: [];
            }

            $options = [];
            foreach ($optionNodes as $opt) {
                $get = function ($tag) use ($opt, $defaultNamespace) {
                    if ($defaultNamespace) {
                        $opt->registerXPathNamespace('ns', $defaultNamespace);
                        $node = $opt->xpath('ns:' . $tag);
                    } else {
                        $node = $opt->xpath($tag);
                    }
                    return isset($node[0]) ? (string) $node[0] : null;
                };

                $options[] = [
                    'category_code' => $get('catc'),
                    'type' => $get('type'),
                    'description' => $get('description'),
                    'size_sqm' => $get('size') ? (int) $get('size') : null,
                    'price_total' => $get('price') ? (float) $get('price') : null,
                    'price_per_person' => $get('price_per_person') ? (float) $get('price_per_person') : null,
                    'price_per_adult' => $get('price_per_adult') ? (float) $get('price_per_adult') : null,
                    'price_per_night' => $get('price_per_night') ? (float) $get('price_per_night') : null,
                    'board' => $get('board') ? (int) $get('board') : null,
                    'room_type' => $get('room_type') ? (int) $get('room_type') : null,
                ];
            }

            $rooms[] = [
                'arrival' => $arrival,
                'departure' => $departure,
                'adults' => $adults !== '' ? (int) $adults : null,
                'options' => $options,
            ];
        }

        return ['rooms' => $rooms];
    }

    /**
     * Format a human-friendly markdown result.
     */
    private function formatMarkdownResult(int $hotelId, string $arrival, string $departure, int $language, array $rooms): string
    {
        $title = 'Hotel Room Availability';
        $md = "# {$title}\n\n";
        $md .= "Hotel ID: `{$hotelId}`\n";
        $md .= "Dates: {$arrival} → {$departure}\n\n";

        foreach ($rooms as $index => $room) {
            $num = $index + 1;
            $md .= "## Room Request {$num}\n";
            if (!empty($room['adults'])) {
                $md .= "Adults: {$room['adults']}\n";
            }
            $md .= "\n";

            if (empty($room['options'])) {
                $md .= "No options available for this occupancy.\n\n";
                continue;
            }

            foreach ($room['options'] as $optIndex => $opt) {
                $line = ($opt['type'] ?? 'Room Option');
                if (!empty($opt['category_code'])) {
                    $line .= " (`{$opt['category_code']}`)";
                }
                $optNumber = $optIndex + 1;
                $md .= "- **Option {$optNumber}**: {$line}\n";
                if (!empty($opt['description'])) {
                    $md .= "  - **Description**: {$opt['description']}\n";
                }
                if (!empty($opt['size_sqm'])) {
                    $md .= "  - **Size**: {$opt['size_sqm']} m²\n";
                }
                if (!empty($opt['board'])) {
                    $boardMap = [1 => 'Breakfast', 2 => 'Half board', 3 => 'Full board', 4 => 'No meals', 5 => 'All inclusive'];
                    $boardText = $boardMap[$opt['board']] ?? (string) $opt['board'];
                    $md .= "  - **Board**: {$boardText}\n";
                }
                if (!empty($opt['room_type'])) {
                    $typeMap = [1 => 'Hotel room', 2 => 'Apartment / Holiday home'];
                    $typeText = $typeMap[$opt['room_type']] ?? (string) $opt['room_type'];
                    $md .= "  - **Type**: {$typeText}\n";
                }
                $priceBits = [];
                if (!empty($opt['price_total'])) $priceBits[] = 'Total €' . number_format($opt['price_total'], 2);
                if (!empty($opt['price_per_person'])) $priceBits[] = 'Per person €' . number_format($opt['price_per_person'], 2);
                if (!empty($opt['price_per_adult'])) $priceBits[] = 'Per adult €' . number_format($opt['price_per_adult'], 2);
                if (!empty($opt['price_per_night'])) $priceBits[] = 'Per night €' . number_format($opt['price_per_night'], 2);
                if (!empty($priceBits)) {
                    $md .= '  - **Pricing**: ' . implode(' | ', $priceBits) . "\n";
                }
            }

            $md .= "\n";
        }

        return $md;
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, \Illuminate\JsonSchema\JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'hotel_id' => $schema->integer()
                ->description('CapCorn Hotel ID to search (default configured).'),

            'arrival' => $schema->string()
                ->description('Arrival date (YYYY-MM-DD).'),

            'departure' => $schema->string()
                ->description('Departure date (YYYY-MM-DD).'),

            'rooms' => $schema->array()
                ->description('Up to 10 room requests. Each room allows up to 15 people, max 8 children (1-17).')
                ->items(
                    $schema->object([
                        'adults' => $schema->integer()->description('Number of adults in the room (1-15).'),
                        'children_ages' => $schema->array()->items($schema->integer()->description('Child age 1-17.'))->description('Ages of children (max 8).'),
                    ])
                ),
        ];
    }
}


