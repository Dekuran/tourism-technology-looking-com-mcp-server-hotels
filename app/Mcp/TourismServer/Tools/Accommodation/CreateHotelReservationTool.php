<?php

namespace App\Mcp\TourismServer\Tools\Accommodation;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Tool;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\JsonSchema\JsonSchema;

class CreateHotelReservationTool extends Tool
{
    protected string $description = <<<'MARKDOWN'
        Create a hotel reservation by importing an OTA_HotelResNotifRQ booking into the accommodation system.
        Provide guest details, stay dates, selected room type, occupancy, and total amount.
    MARKDOWN;

    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'hotel_id' => 'nullable|integer',
            'room_type_code' => 'required|string|max:8',
            'number_of_units' => 'required|integer|min:1',
            'meal_plan_code' => 'nullable|integer|in:1,2,3,4,5',

            'adults' => 'required|integer|min:1',
            'children_ages' => 'nullable|array|max:8',
            'children_ages.*' => 'integer|min:1|max:17',

            'start' => 'required|date_format:Y-m-d',
            'end' => 'required|date_format:Y-m-d|after:start',

            'total_amount' => 'required|numeric|min:0',
            'currency_code' => 'nullable|string|size:3',

            'services' => 'nullable|array',
            'services.*.name' => 'required_with:services|string|max:120',
            'services.*.quantity' => 'required_with:services|integer|min:1',
            'services.*.unit_amount' => 'required_with:services|numeric|min:0',

            'guest.salutation' => 'nullable|string|max:20',
            'guest.given_name' => 'required|string|max:60',
            'guest.surname' => 'required|string|max:60',
            'guest.phone_number' => 'nullable|string|max:40',
            'guest.email' => 'required|email',
            'guest.address_line' => 'nullable|string|max:120',
            'guest.city_name' => 'nullable|string|max:60',
            'guest.postal_code' => 'nullable|string|max:20',
            'guest.country_code' => 'nullable|string|size:2',
            'comment' => 'nullable|string|max:200',

            'res_id_value' => 'required|string|max:20',
            'res_id_source' => 'required|string|max:60',
        ]);

        $endpoint = config('services.hotel_availability.reservation_endpoint');
        $pin = config('services.hotel_availability.reservation_pin');
        $hotelId = $validated['hotel_id'] ?? config('services.hotel_availability.default_hotel_id');

        $url = $endpoint . '?hotelId=' . urlencode((string) $hotelId) . '&pin=' . urlencode((string) $pin);

        $xml = $this->buildReservationXml(
            hotelId: (int) $hotelId,
            roomTypeCode: $validated['room_type_code'],
            numberOfUnits: (int) $validated['number_of_units'],
            mealPlanCode: $validated['meal_plan_code'] ?? null,
            adults: (int) $validated['adults'],
            childrenAges: $validated['children_ages'] ?? [],
            start: $validated['start'],
            end: $validated['end'],
            totalAmount: (float) $validated['total_amount'],
            currencyCode: $validated['currency_code'] ?? 'EUR',
            services: $validated['services'] ?? [],
            guest: $validated['guest'],
            comment: $validated['comment'] ?? null,
            resIdValue: $validated['res_id_value'],
            resIdSource: $validated['res_id_source'],
        );

        try {
            $response = Http::withHeaders([
                    'Content-Type' => 'application/xml',
                    'Accept' => 'application/xml',
                ])
                ->withBody($xml, 'application/xml')
                ->send('POST', $url);

            if (!$response->ok()) {
                return Response::text('Reservation service error: HTTP ' . $response->status());
            }

            $currency = $validated['currency_code'] ?? 'EUR';
            return Response::text("Reservation submitted. Please check CapCorn for confirmation.\n\nHotel ID: {$hotelId}\nResID: {$validated['res_id_value']} ({$validated['res_id_source']})\nDates: {$validated['start']} → {$validated['end']}\nUnits: {$validated['number_of_units']} x {$validated['room_type_code']}\nTotal: {$validated['total_amount']} {$currency}");
        } catch (\Throwable $e) {
            Log::error('CreateHotelReservationTool error', ['error' => $e->getMessage()]);
            return Response::text('Failed to create reservation. Please try again later.');
        }
    }

    private function buildReservationXml(
        int $hotelId,
        string $roomTypeCode,
        int $numberOfUnits,
        ?int $mealPlanCode,
        int $adults,
        array $childrenAges,
        string $start,
        string $end,
        float $totalAmount,
        string $currencyCode,
        array $services,
        array $guest,
        ?string $comment,
        string $resIdValue,
        string $resIdSource,
    ): string {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="utf-8"?><OTA_HotelResNotifRQ xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" Version="1" xmlns="http://www.opentravel.org/OTA/2003/05"/>');

        // POS
        $pos = $xml->addChild('POS');
        $source = $pos->addChild('Source');
        $source->addAttribute('AgentDutyCode', 'Hackathon');

        $hotelReservations = $xml->addChild('HotelReservations');
        $hotelReservation = $hotelReservations->addChild('HotelReservation');
        $hotelReservation->addAttribute('CreateDateTime', date('c'));
        $hotelReservation->addAttribute('ResStatus', 'Book');

        $roomStays = $hotelReservation->addChild('RoomStays');
        $roomStay = $roomStays->addChild('RoomStay');

        $roomTypes = $roomStay->addChild('RoomTypes');
        $roomType = $roomTypes->addChild('RoomType');
        $roomType->addAttribute('NumberOfUnits', (string) $numberOfUnits);
        $roomType->addAttribute('RoomTypeCode', $roomTypeCode);

        $ratePlans = $roomStay->addChild('RatePlans');
        $ratePlan = $ratePlans->addChild('RatePlan');
        if ($mealPlanCode !== null) {
            $mealsIncluded = $ratePlan->addChild('MealsIncluded');
            $mealsIncluded->addAttribute('MealPlanCodes', (string) $mealPlanCode);
        }

        $guestCounts = $roomStay->addChild('GuestCounts');
        $guestCounts->addAttribute('IsPerRoom', 'true');
        $guestCountAdult = $guestCounts->addChild('GuestCount');
        $guestCountAdult->addAttribute('AgeQualifyingCode', '10');
        $guestCountAdult->addAttribute('Count', (string) $adults);

        foreach ($childrenAges as $age) {
            $guestCountChild = $guestCounts->addChild('GuestCount');
            $guestCountChild->addAttribute('AgeQualifyingCode', '8');
            $guestCountChild->addAttribute('Age', (string) $age);
            $guestCountChild->addAttribute('Count', '1');
        }

        $timeSpan = $roomStay->addChild('TimeSpan');
        $timeSpan->addAttribute('Start', $start);
        $timeSpan->addAttribute('End', $end);

        $total = $roomStay->addChild('Total');
        $total->addAttribute('AmountAfterTax', number_format($totalAmount, 2, '.', ''));
        $total->addAttribute('CurrencyCode', $currencyCode);

        $basicPropertyInfo = $roomStay->addChild('BasicPropertyInfo');
        $basicPropertyInfo->addAttribute('HotelCode', (string) $hotelId);

        if (!empty($services)) {
            $servicesNode = $hotelReservation->addChild('Services');
            foreach ($services as $service) {
                $serviceNode = $servicesNode->addChild('Service');
                $serviceNode->addAttribute('Quantity', (string) $service['quantity']);
                $details = $serviceNode->addChild('ServiceDetails');
                $desc = $details->addChild('ServiceDescription');
                $desc->addAttribute('Name', $service['name']);
                $price = $serviceNode->addChild('Price');
                $base = $price->addChild('Base');
                $base->addAttribute('AmountAfterTax', number_format((float) $service['unit_amount'], 2, '.', ''));
            }
        }

        $resGuests = $hotelReservation->addChild('ResGuests');
        $resGuest = $resGuests->addChild('ResGuest');
        $profiles = $resGuest->addChild('Profiles');
        $profileInfo = $profiles->addChild('ProfileInfo');
        $profile = $profileInfo->addChild('Profile');
        $customer = $profile->addChild('Customer');
        if (!empty($guest['salutation'])) {
            $customer->addChild('Language', 'de');
        }
        $personName = $customer->addChild('PersonName');
        if (!empty($guest['salutation'])) {
            $personName->addChild('NamePrefix', $guest['salutation']);
        }
        $personName->addChild('GivenName', $guest['given_name']);
        $personName->addChild('Surname', $guest['surname']);
        if (!empty($guest['phone_number'])) {
            $customer->addChild('Telephone')->addAttribute('PhoneNumber', $guest['phone_number']);
        }
        $customer->addChild('Email', $guest['email']);

        $addressNeeded = !empty($guest['address_line']) || !empty($guest['city_name']) || !empty($guest['postal_code']) || !empty($guest['country_code']);
        if ($addressNeeded) {
            $address = $customer->addChild('Address');
            if (!empty($guest['address_line'])) $address->addChild('AddressLine', $guest['address_line']);
            if (!empty($guest['city_name'])) $address->addChild('CityName', $guest['city_name']);
            if (!empty($guest['postal_code'])) $address->addChild('PostalCode', $guest['postal_code']);
            if (!empty($guest['country_code'])) $address->addChild('CountryName')->addAttribute('Code', $guest['country_code']);
        }

        if (!empty($comment)) {
            $comments = $resGuest->addChild('Comments');
            $commentNode = $comments->addChild('Comment');
            $commentNode->addChild('ListItem', $comment);
        }

        $resGlobalInfo = $hotelReservation->addChild('ResGlobalInfo');
        $hotelReservationIDs = $resGlobalInfo->addChild('HotelReservationIDs');
        $hotelReservationID = $hotelReservationIDs->addChild('HotelReservationID');
        $hotelReservationID->addAttribute('ResID_Value', $resIdValue);
        $hotelReservationID->addAttribute('ResID_Source', $resIdSource);

        return $xml->asXML();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'hotel_id' => $schema->integer()->description('CapCorn Hotel ID (default from config).'),
            'room_type_code' => $schema->string()->description('RoomTypeCode from availability (max 8).'),
            'number_of_units' => $schema->integer()->description('Number of units to book.'),
            'meal_plan_code' => $schema->integer()->description('MealsIncluded code: 1=Breakfast, 2=Half board, 3=Full board, 4=No meals, 5=All inclusive.'),

            'adults' => $schema->integer()->description('Number of adults.'),
            'children_ages' => $schema->array()->items($schema->integer()->description('Child age 1-17.'))->description('Children ages.'),

            'start' => $schema->string()->description('Arrival date YYYY-MM-DD.'),
            'end' => $schema->string()->description('Departure date YYYY-MM-DD.'),

            'total_amount' => $schema->number()->description('Total AmountAfterTax for all units.'),
            'currency_code' => $schema->string()->description('Currency code (EUR).'),

            'services' => $schema->array()->items(
                $schema->object([
                    'name' => $schema->string()->description('Service name.'),
                    'quantity' => $schema->integer()->description('Quantity.'),
                    'unit_amount' => $schema->number()->description('Unit price (AmountAfterTax).'),
                ])
            )->description('Optional additional services.'),

            'guest' => $schema->object([
                'salutation' => $schema->string()->description('Optional salutation.'),
                'given_name' => $schema->string()->description('Guest given name.'),
                'surname' => $schema->string()->description('Guest surname.'),
                'phone_number' => $schema->string()->description('Phone number.'),
                'email' => $schema->string()->description('Email.'),
                'address_line' => $schema->string()->description('Address line.'),
                'city_name' => $schema->string()->description('City.'),
                'postal_code' => $schema->string()->description('Postal code.'),
                'country_code' => $schema->string()->description('Country code (2-letter).'),
            ])->description('Guest profile and contact info.'),

            'comment' => $schema->string()->description('Optional booking comment (max 200 chars).'),

            'res_id_value' => $schema->string()->description('External unique booking ID (ResID_Value).'),
            'res_id_source' => $schema->string()->description('Booking source/channel (ResID_Source).'),
        ];
    }
}


