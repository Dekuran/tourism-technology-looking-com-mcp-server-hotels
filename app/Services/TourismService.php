<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TourismService
{
    private array $destinations = [
        [
            'id' => 1,
            'name' => 'Vienna',
            'country' => 'Austria',
            'type' => 'city',
            'description' => 'Capital city known for imperial palaces, museums, and classical music heritage.',
            'latitude' => 48.2082,
            'longitude' => 16.3738,
        ],
        [
            'id' => 2,
            'name' => 'Salzburg',
            'country' => 'Austria',
            'type' => 'city',
            'description' => 'Historic city, birthplace of Mozart, and gateway to the Alps.',
            'latitude' => 47.8095,
            'longitude' => 13.0550,
        ],
        [
            'id' => 3,
            'name' => 'Innsbruck',
            'country' => 'Austria',
            'type' => 'mountain',
            'description' => 'Alpine city surrounded by mountains, famous for winter sports.',
            'latitude' => 47.2692,
            'longitude' => 11.4041,
        ],
        [
            'id' => 4,
            'name' => 'Hallstatt',
            'country' => 'Austria',
            'type' => 'village',
            'description' => 'Picturesque lakeside village in the Salzkammergut region.',
            'latitude' => 47.5622,
            'longitude' => 13.6493,
        ],
    ];

    private array $attractions = [
        // Vienna attractions
        [
            'id' => 101,
            'name' => 'Schönbrunn Palace',
            'category' => 'Historical Site',
            'description' => 'Former imperial summer residence with magnificent gardens.',
            'latitude' => 48.1845,
            'longitude' => 16.3122,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 26.00,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '9:00 AM - 5:30 PM',
            'booking_details' => 'Skip-the-line access to the palace and gardens. Includes audio guide.',
            'tags' => ['history', 'architecture', 'culture', 'family-friendly', 'romantic', 'photography'],
        ],
        [
            'id' => 102,
            'name' => 'St. Stephen\'s Cathedral',
            'category' => 'Religious Site',
            'description' => 'Iconic Gothic cathedral in the heart of Vienna.',
            'latitude' => 48.2085,
            'longitude' => 16.3730,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 15.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '6:00 AM - 10:00 PM',
            'booking_details' => 'Guided tour including tower climb with panoramic views.',
            'tags' => ['architecture', 'religious', 'history', 'culture', 'photography'],
        ],
        [
            'id' => 103,
            'name' => 'Belvedere Palace',
            'category' => 'Museum',
            'description' => 'Baroque palace complex housing Austrian art including "The Kiss" by Klimt.',
            'latitude' => 48.1915,
            'longitude' => 16.3809,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 22.00,
            'currency' => 'EUR',
            'duration_minutes' => 150,
            'opening_hours' => '9:00 AM - 6:00 PM',
            'booking_details' => 'Full access to Upper and Lower Belvedere. See Klimt\'s masterpieces.',
            'tags' => ['art', 'history', 'architecture', 'culture', 'romantic', 'photography'],
        ],
        [
            'id' => 104,
            'name' => 'Vienna State Opera',
            'category' => 'Entertainment',
            'description' => 'One of the world\'s leading opera houses.',
            'latitude' => 48.2030,
            'longitude' => 16.3691,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 35.00,
            'currency' => 'EUR',
            'duration_minutes' => 180,
            'opening_hours' => 'Performances at 7:00 PM',
            'booking_details' => 'Evening opera performance with reserved seating.',
            'tags' => ['music', 'culture', 'architecture', 'romantic', 'luxury'],
        ],
        [
            'id' => 105,
            'name' => 'Prater Park',
            'category' => 'Park',
            'description' => 'Large public park with the famous Giant Ferris Wheel.',
            'latitude' => 48.2167,
            'longitude' => 16.3967,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 13.50,
            'currency' => 'EUR',
            'duration_minutes' => 30,
            'opening_hours' => '10:00 AM - 11:45 PM',
            'booking_details' => 'Giant Ferris Wheel ride ticket. Skip the queue.',
            'tags' => ['family-friendly', 'adventure', 'photography', 'budget', 'outdoor'],
        ],
        // Salzburg attractions
        [
            'id' => 201,
            'name' => 'Hohensalzburg Fortress',
            'category' => 'Historical Site',
            'description' => 'One of Europe\'s largest medieval castles overlooking the city.',
            'latitude' => 47.7951,
            'longitude' => 13.0477,
            'destination_id' => 2,
            'bookable' => true,
            'price' => 18.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '9:30 AM - 5:00 PM',
            'booking_details' => 'Castle tour with fortress museum and viewing platform access.',
            'tags' => ['history', 'architecture', 'culture', 'family-friendly', 'photography'],
        ],
        [
            'id' => 202,
            'name' => 'Mozart\'s Birthplace',
            'category' => 'Museum',
            'description' => 'Historic house where Wolfgang Amadeus Mozart was born.',
            'latitude' => 47.8000,
            'longitude' => 13.0438,
            'destination_id' => 2,
            'bookable' => true,
            'price' => 12.00,
            'currency' => 'EUR',
            'duration_minutes' => 60,
            'opening_hours' => '9:00 AM - 5:30 PM',
            'booking_details' => 'Self-guided tour through Mozart\'s childhood home with audio guide.',
            'tags' => ['music', 'history', 'culture', 'budget'],
        ],
        [
            'id' => 203,
            'name' => 'Mirabell Palace and Gardens',
            'category' => 'Historical Site',
            'description' => 'Beautiful baroque palace with stunning gardens.',
            'latitude' => 47.8057,
            'longitude' => 13.0418,
            'destination_id' => 2,
            'bookable' => false,
            'price' => 0.00,
            'currency' => 'EUR',
            'tags' => ['architecture', 'romantic', 'photography', 'budget', 'family-friendly'],
        ],
        [
            'id' => 204,
            'name' => 'Salzburg Cathedral',
            'category' => 'Religious Site',
            'description' => 'Magnificent baroque cathedral where Mozart was baptized.',
            'latitude' => 47.7980,
            'longitude' => 13.0477,
            'destination_id' => 2,
            'bookable' => false,
            'price' => 0.00,
            'currency' => 'EUR',
            'tags' => ['religious', 'architecture', 'history', 'culture', 'budget'],
        ],
        // Innsbruck attractions
        [
            'id' => 301,
            'name' => 'Golden Roof',
            'category' => 'Historical Site',
            'description' => 'Iconic landmark with 2,657 gilded copper tiles.',
            'latitude' => 47.2683,
            'longitude' => 11.3933,
            'destination_id' => 3,
            'bookable' => false,
            'price' => 0.00,
            'currency' => 'EUR',
            'tags' => ['history', 'architecture', 'photography', 'budget'],
        ],
        [
            'id' => 302,
            'name' => 'Nordkette Cable Car',
            'category' => 'Attraction',
            'description' => 'Cable car taking visitors from the city center to the mountains.',
            'latitude' => 47.2647,
            'longitude' => 11.3875,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 38.50,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '8:30 AM - 5:30 PM',
            'booking_details' => 'Round-trip cable car to Nordkette mountains with viewing platform.',
            'tags' => ['adventure', 'nature', 'photography', 'family-friendly', 'outdoor'],
        ],
        [
            'id' => 303,
            'name' => 'Ambras Castle',
            'category' => 'Historical Site',
            'description' => 'Renaissance castle with art collections and armory.',
            'latitude' => 47.2531,
            'longitude' => 11.4278,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 15.00,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '10:00 AM - 5:00 PM',
            'booking_details' => 'Castle tour including armory and art collections.',
            'tags' => ['history', 'art', 'architecture', 'culture'],
        ],
        [
            'id' => 304,
            'name' => 'Bergisel Ski Jump',
            'category' => 'Sports Venue',
            'description' => 'Modern ski jump with panoramic views of the Alps.',
            'latitude' => 47.2466,
            'longitude' => 11.4007,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 10.00,
            'currency' => 'EUR',
            'duration_minutes' => 45,
            'opening_hours' => '9:00 AM - 6:00 PM',
            'booking_details' => 'Access to ski jump tower and panorama viewing platform.',
            'tags' => ['sports', 'adventure', 'architecture', 'photography', 'budget'],
        ],
        // Hallstatt attractions
        [
            'id' => 401,
            'name' => 'Hallstatt Salt Mine',
            'category' => 'Historical Site',
            'description' => 'Ancient salt mine, one of the oldest in the world.',
            'latitude' => 47.5644,
            'longitude' => 13.6506,
            'destination_id' => 4,
            'bookable' => true,
            'price' => 32.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '9:30 AM - 4:30 PM',
            'booking_details' => 'Guided underground tour with funicular railway ride.',
            'tags' => ['history', 'adventure', 'family-friendly', 'culture'],
        ],
        [
            'id' => 402,
            'name' => 'Hallstatt Skywalk',
            'category' => 'Viewpoint',
            'description' => 'Viewing platform with breathtaking views over the lake and village.',
            'latitude' => 47.5630,
            'longitude' => 13.6510,
            'destination_id' => 4,
            'bookable' => true,
            'price' => 8.00,
            'currency' => 'EUR',
            'duration_minutes' => 30,
            'opening_hours' => '9:00 AM - 6:00 PM',
            'booking_details' => 'Access to Skywalk viewing platform.',
            'tags' => ['nature', 'photography', 'adventure', 'budget', 'romantic'],
        ],
        [
            'id' => 403,
            'name' => 'Lake Hallstatt',
            'category' => 'Nature',
            'description' => 'Beautiful alpine lake surrounded by mountains.',
            'latitude' => 47.5622,
            'longitude' => 13.6493,
            'destination_id' => 4,
            'bookable' => false,
            'price' => 0.00,
            'currency' => 'EUR',
            'tags' => ['nature', 'photography', 'romantic', 'budget', 'outdoor', 'family-friendly'],
        ],
        // Vienna Restaurants and Cafes
        [
            'id' => 501,
            'name' => 'Cafe Schwarzenberg',
            'category' => 'Cafe',
            'description' => 'Historic Viennese coffeehouse with traditional atmosphere and excellent pastries.',
            'latitude' => 48.2019,
            'longitude' => 16.3750,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 8.50,
            'currency' => 'EUR',
            'duration_minutes' => 60,
            'opening_hours' => '7:00 AM - 11:00 PM',
            'booking_details' => 'Reserved table for coffee and Viennese pastries. Includes traditional coffee service.',
            'tags' => ['food', 'culture', 'history', 'romantic', 'budget', 'family-friendly'],
        ],
        [
            'id' => 502,
            'name' => 'Cafe Central',
            'category' => 'Cafe',
            'description' => 'Famous historic coffeehouse where intellectuals and artists once gathered.',
            'latitude' => 48.2104,
            'longitude' => 16.3708,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 12.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '7:30 AM - 10:00 PM',
            'booking_details' => 'Reserved table in the historic main hall. Includes coffee and cake selection.',
            'tags' => ['food', 'culture', 'history', 'romantic', 'luxury', 'architecture'],
        ],
        [
            'id' => 503,
            'name' => 'Steirereck',
            'category' => 'Restaurant',
            'description' => 'Michelin-starred restaurant offering innovative Austrian cuisine.',
            'latitude' => 48.2008,
            'longitude' => 16.3756,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 180.00,
            'currency' => 'EUR',
            'duration_minutes' => 180,
            'opening_hours' => '12:00 PM - 2:00 PM, 6:30 PM - 10:00 PM',
            'booking_details' => 'Fine dining experience with tasting menu. Wine pairing available.',
            'tags' => ['food', 'luxury', 'romantic', 'culture', 'gourmet'],
        ],
        [
            'id' => 504,
            'name' => 'Figlmüller',
            'category' => 'Restaurant',
            'description' => 'Traditional Viennese restaurant famous for its Wiener Schnitzel.',
            'latitude' => 48.2085,
            'longitude' => 16.3730,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 25.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '11:00 AM - 11:00 PM',
            'booking_details' => 'Traditional Austrian dinner with Wiener Schnitzel and local beer.',
            'tags' => ['food', 'culture', 'family-friendly', 'traditional', 'budget'],
        ],
        [
            'id' => 505,
            'name' => 'Cafe Sacher',
            'category' => 'Cafe',
            'description' => 'Home of the original Sacher Torte, Vienna\'s most famous cake.',
            'latitude' => 48.2030,
            'longitude' => 16.3691,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 15.00,
            'currency' => 'EUR',
            'duration_minutes' => 75,
            'opening_hours' => '8:00 AM - 12:00 AM',
            'booking_details' => 'Reserved table with original Sacher Torte and coffee service.',
            'tags' => ['food', 'culture', 'history', 'romantic', 'luxury', 'family-friendly'],
        ],
        [
            'id' => 506,
            'name' => 'Plachutta',
            'category' => 'Restaurant',
            'description' => 'Traditional restaurant specializing in Tafelspitz (boiled beef).',
            'latitude' => 48.2104,
            'longitude' => 16.3708,
            'destination_id' => 1,
            'bookable' => true,
            'price' => 35.00,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '11:30 AM - 11:00 PM',
            'booking_details' => 'Traditional Austrian lunch with Tafelspitz and wine selection.',
            'tags' => ['food', 'culture', 'traditional', 'family-friendly', 'moderate'],
        ],
        // Salzburg Restaurants and Cafes
        [
            'id' => 601,
            'name' => 'Cafe Tomaselli',
            'category' => 'Cafe',
            'description' => 'Historic coffeehouse where Mozart used to visit, dating back to 1700.',
            'latitude' => 47.8000,
            'longitude' => 13.0438,
            'destination_id' => 2,
            'bookable' => true,
            'price' => 9.00,
            'currency' => 'EUR',
            'duration_minutes' => 60,
            'opening_hours' => '7:00 AM - 7:00 PM',
            'booking_details' => 'Historic coffeehouse experience with traditional pastries and coffee.',
            'tags' => ['food', 'culture', 'history', 'music', 'romantic', 'budget'],
        ],
        [
            'id' => 602,
            'name' => 'St. Peter Stiftskeller',
            'category' => 'Restaurant',
            'description' => 'Europe\'s oldest restaurant, serving traditional Austrian cuisine since 803 AD.',
            'latitude' => 47.7980,
            'longitude' => 13.0477,
            'destination_id' => 2,
            'bookable' => true,
            'price' => 45.00,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '11:30 AM - 10:00 PM',
            'booking_details' => 'Historic dining experience in Europe\'s oldest restaurant with traditional menu.',
            'tags' => ['food', 'culture', 'history', 'romantic', 'luxury', 'traditional'],
        ],
        [
            'id' => 603,
            'name' => 'Zum Fidelen Affen',
            'category' => 'Restaurant',
            'description' => 'Cozy traditional restaurant with excellent local cuisine and beer.',
            'latitude' => 47.8000,
            'longitude' => 13.0438,
            'destination_id' => 2,
            'bookable' => true,
            'price' => 28.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '5:00 PM - 11:00 PM',
            'booking_details' => 'Traditional Austrian dinner with local specialties and beer.',
            'tags' => ['food', 'culture', 'traditional', 'family-friendly', 'moderate'],
        ],
        // Innsbruck Restaurants and Cafes
        [
            'id' => 701,
            'name' => 'Cafe Central Innsbruck',
            'category' => 'Cafe',
            'description' => 'Historic coffeehouse in the heart of Innsbruck with mountain views.',
            'latitude' => 47.2683,
            'longitude' => 11.3933,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 7.50,
            'currency' => 'EUR',
            'duration_minutes' => 60,
            'opening_hours' => '7:00 AM - 8:00 PM',
            'booking_details' => 'Coffee and cake in historic setting with Alpine atmosphere.',
            'tags' => ['food', 'culture', 'history', 'family-friendly', 'budget', 'outdoor'],
        ],
        [
            'id' => 702,
            'name' => 'Restaurant Lichtblick',
            'category' => 'Restaurant',
            'description' => 'Modern restaurant with panoramic views of the Alps and innovative cuisine.',
            'latitude' => 47.2647,
            'longitude' => 11.3875,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 65.00,
            'currency' => 'EUR',
            'duration_minutes' => 150,
            'opening_hours' => '12:00 PM - 2:00 PM, 6:00 PM - 10:00 PM',
            'booking_details' => 'Fine dining with Alpine views and modern Austrian cuisine.',
            'tags' => ['food', 'luxury', 'romantic', 'nature', 'gourmet', 'photography'],
        ],
        [
            'id' => 703,
            'name' => 'Gasthaus Weisses Rössl',
            'category' => 'Restaurant',
            'description' => 'Traditional Tyrolean inn serving hearty local specialties.',
            'latitude' => 47.2683,
            'longitude' => 11.3933,
            'destination_id' => 3,
            'bookable' => true,
            'price' => 22.00,
            'currency' => 'EUR',
            'duration_minutes' => 90,
            'opening_hours' => '11:00 AM - 10:00 PM',
            'booking_details' => 'Traditional Tyrolean dinner with local beer and specialties.',
            'tags' => ['food', 'culture', 'traditional', 'family-friendly', 'budget'],
        ],
        // Hallstatt Restaurants and Cafes
        [
            'id' => 801,
            'name' => 'Cafe Derbl',
            'category' => 'Cafe',
            'description' => 'Charming lakeside cafe with stunning views of Lake Hallstatt.',
            'latitude' => 47.5622,
            'longitude' => 13.6493,
            'destination_id' => 4,
            'bookable' => true,
            'price' => 8.00,
            'currency' => 'EUR',
            'duration_minutes' => 60,
            'opening_hours' => '8:00 AM - 6:00 PM',
            'booking_details' => 'Lakeside coffee and cake with panoramic lake views.',
            'tags' => ['food', 'nature', 'romantic', 'photography', 'budget', 'outdoor'],
        ],
        [
            'id' => 802,
            'name' => 'Restaurant Seewirt Zauner',
            'category' => 'Restaurant',
            'description' => 'Historic lakeside restaurant serving traditional Austrian cuisine.',
            'latitude' => 47.5622,
            'longitude' => 13.6493,
            'destination_id' => 4,
            'bookable' => true,
            'price' => 35.00,
            'currency' => 'EUR',
            'duration_minutes' => 120,
            'opening_hours' => '11:30 AM - 9:00 PM',
            'booking_details' => 'Lakeside dining with traditional Austrian dishes and local wine.',
            'tags' => ['food', 'culture', 'nature', 'romantic', 'traditional', 'moderate'],
        ],
    ];

    /**
     * Cache key prefix for bookings
     */
    private const BOOKING_CACHE_PREFIX = 'booking:';
    private const BOOKING_INDEX_KEY = 'bookings:index';
    private const BOOKING_TTL = 7200; // 2 hours in seconds

    /**
     * Cache key prefix for restaurant reservations
     */
    private const RESERVATION_CACHE_PREFIX = 'reservation:';
    private const RESERVATION_INDEX_KEY = 'reservations:index';
    private const RESERVATION_TTL = 7200; // 2 hours in seconds

    /**
     * Cache key prefix for user profiles
     */
    private const USER_PROFILE_CACHE_PREFIX = 'user_profile:';
    private const USER_PROFILE_TTL = 7200; // 2 days in seconds

    public function searchDestinations(string $query, ?string $country = null, ?string $type = null): array
    {
        $results = array_filter($this->destinations, function ($destination) use ($query, $country, $type) {
            $matchesQuery = stripos($destination['name'], $query) !== false ||
                          stripos($destination['description'], $query) !== false;
            
            $matchesCountry = $country === null || stripos($destination['country'], $country) !== false;
            $matchesType = $type === null || stripos($destination['type'], $type) !== false;

            return $matchesQuery && $matchesCountry && $matchesType;
        });

        return array_values($results);
    }

    public function getDestination(int $destinationId): ?array
    {
        foreach ($this->destinations as $destination) {
            if ($destination['id'] === $destinationId) {
                return $destination;
            }
        }
        return null;
    }

    public function getDestinationByName(string $name): ?array
    {
        foreach ($this->destinations as $destination) {
            if (strcasecmp($destination['name'], $name) === 0) {
                return $destination;
            }
        }
        return null;
    }

    
    public function findNearbyAttractions(?int $destinationId = null, ?float $latitude = null, ?float $longitude = null, int $radiusKm = 10): array
    {
        // If destination_id is provided, get its coordinates
        if ($destinationId !== null) {
            $destination = $this->getDestination($destinationId);
            if ($destination) {
                $latitude = $destination['latitude'];
                $longitude = $destination['longitude'];
            }
        }

        // If no coordinates available, return empty
        if ($latitude === null || $longitude === null) {
            return [];
        }

        // Filter attractions by distance
        $nearbyAttractions = [];
        foreach ($this->attractions as $attraction) {
            $distance = $this->calculateDistance(
                $latitude,
                $longitude,
                $attraction['latitude'],
                $attraction['longitude']
            );

            if ($distance <= $radiusKm) {
                $nearbyAttractions[] = array_merge($attraction, [
                    'distance_km' => round($distance, 2),
                ]);
            }
        }

        // Sort by distance
        usort($nearbyAttractions, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);

        return $nearbyAttractions;
    }

    /**
     * Get attraction by ID
     */
    public function getAttraction(int $attractionId): ?array
    {
        foreach ($this->attractions as $attraction) {
            if ($attraction['id'] === $attractionId) {
                return $attraction;
            }
        }
        return null;
    }

    /**
     * Get top attractions for a destination
     */
    public function getTopAttractions(?int $destinationId = null, int $limit = 4): array
    {
        if ($destinationId === null) {
            return [];
        }

        $attractions = array_filter($this->attractions, function ($attraction) use ($destinationId) {
            return $attraction['destination_id'] === $destinationId;
        });

        // Prioritize bookable attractions
        usort($attractions, function ($a, $b) {
            if ($a['bookable'] === $b['bookable']) {
                return 0;
            }
            return $a['bookable'] ? -1 : 1;
        });

        return array_slice(array_values($attractions), 0, $limit);
    }

    /**
     * Get restaurants and cafes for a destination
     */
    public function getRestaurantsAndCafes(?int $destinationId = null, ?string $destinationName = null, int $limit = 6): array
    {
        // Get destination ID from name if provided
        if ($destinationName !== null && $destinationId === null) {
            $destination = $this->getDestinationByName($destinationName);
            if ($destination) {
                $destinationId = $destination['id'];
            }
        }

        if ($destinationId === null) {
            return [];
        }

        // Filter only restaurants and cafes
        $restaurants = array_filter($this->attractions, function ($attraction) use ($destinationId) {
            return $attraction['destination_id'] === $destinationId &&
                   in_array($attraction['category'], ['Restaurant', 'Cafe']);
        });

        // Sort by price (budget-friendly first, then moderate, then luxury)
        usort($restaurants, function ($a, $b) {
            return ($a['price'] ?? 0) <=> ($b['price'] ?? 0);
        });

        return array_slice(array_values($restaurants), 0, $limit);
    }

    /**
     * Prepare a booking (creates a pending booking)
     */
    public function prepareBooking(
        int $attractionId, 
        int $numberOfTickets, 
        string $visitDate, 
        string $visitorName, 
        string $visitorEmail,
        array $paymentDetails
    ): ?array
    {
        $attraction = $this->getAttraction($attractionId);
        
        if (!$attraction) {
            return null;
        }

        if (!$attraction['bookable']) {
            return null;
        }

        $bookingId = 'BKG-' . strtoupper(substr(md5(uniqid()), 0, 8));
        $totalAmount = $attraction['price'] * $numberOfTickets;

        $booking = [
            'booking_id' => $bookingId,
            'attraction_id' => $attractionId,
            'attraction_name' => $attraction['name'],
            'category' => $attraction['category'],
            'number_of_tickets' => $numberOfTickets,
            'price_per_ticket' => $attraction['price'],
            'total_amount' => $totalAmount,
            'currency' => $attraction['currency'],
            'visit_date' => $visitDate,
            'visitor_name' => $visitorName,
            'visitor_email' => $visitorEmail,
            'payment_details' => $paymentDetails,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'confirmed_at' => null,
            'booking_details' => $attraction['booking_details'],
            'opening_hours' => $attraction['opening_hours'],
            'duration_minutes' => $attraction['duration_minutes'],
        ];

        // Store booking in cache for 24 hours
        Cache::put(self::BOOKING_CACHE_PREFIX . $bookingId, $booking, self::BOOKING_TTL);
        
        // Add to booking index
        $this->addToBookingIndex($bookingId);

        Log::info('Booking prepared and cached', [
            'booking_id' => $bookingId, 
            'attraction' => $attraction['name'],
            'card_type' => $paymentDetails['card_type'] ?? 'Unknown'
        ]);

        return $booking;
    }

    /**
     * Confirm a booking
     */
    public function confirmBooking(string $bookingId, ?string $paymentTransactionId = null): ?array
    {
        $booking = Cache::get(self::BOOKING_CACHE_PREFIX . $bookingId);
        
        if (!$booking) {
            return null;
        }

        $booking['status'] = 'confirmed';
        $booking['confirmed_at'] = date('Y-m-d H:i:s');
        
        if ($paymentTransactionId) {
            $booking['payment_transaction_id'] = $paymentTransactionId;
        }

        // Generate ticket numbers
        $tickets = [];
        for ($i = 1; $i <= $booking['number_of_tickets']; $i++) {
            $tickets[] = 'TKT-' . strtoupper(substr(md5($bookingId . $i), 0, 10));
        }
        $booking['ticket_numbers'] = $tickets;

        // Update booking in cache
        Cache::put(self::BOOKING_CACHE_PREFIX . $bookingId, $booking, self::BOOKING_TTL);

        Log::info('Booking confirmed and cached', ['booking_id' => $bookingId]);

        return $booking;
    }

    /**
     * Get booking by ID
     */
    public function getBooking(string $bookingId): ?array
    {
        return Cache::get(self::BOOKING_CACHE_PREFIX . $bookingId);
    }

    /**
     * Cancel a booking
     */
    public function cancelBooking(string $bookingId): bool
    {
        $booking = Cache::get(self::BOOKING_CACHE_PREFIX . $bookingId);
        
        if (!$booking) {
            return false;
        }

        $booking['status'] = 'cancelled';
        $booking['cancelled_at'] = date('Y-m-d H:i:s');

        // Update booking in cache
        Cache::put(self::BOOKING_CACHE_PREFIX . $bookingId, $booking, self::BOOKING_TTL);

        Log::info('Booking cancelled and cached', ['booking_id' => $bookingId]);

        return true;
    }

    /**
     * Get all bookings
     */
    public function getAllBookings(): array
    {
        $bookingIds = Cache::get(self::BOOKING_INDEX_KEY, []);
        $bookings = [];
        
        foreach ($bookingIds as $bookingId) {
            $booking = Cache::get(self::BOOKING_CACHE_PREFIX . $bookingId);
            if ($booking) {
                $bookings[] = $booking;
            }
        }
        
        return $bookings;
    }

    /**
     * Add booking ID to index
     */
    private function addToBookingIndex(string $bookingId): void
    {
        $bookingIds = Cache::get(self::BOOKING_INDEX_KEY, []);
        
        if (!in_array($bookingId, $bookingIds)) {
            $bookingIds[] = $bookingId;
            Cache::put(self::BOOKING_INDEX_KEY, $bookingIds, self::BOOKING_TTL);
        }
    }

    /**
     * Prepare a restaurant/cafe reservation (no credit card needed)
     */
    public function prepareRestaurantReservation(
        int $attractionId,
        int $numberOfPeople,
        string $reservationDate,
        string $reservationTime,
        string $guestName,
        string $guestEmail,
        ?string $specialRequests = null
    ): ?array
    {
        $attraction = $this->getAttraction($attractionId);
        
        if (!$attraction) {
            return null;
        }

        // Check if attraction is a restaurant or cafe
        if (!in_array($attraction['category'], ['Restaurant', 'Cafe'])) {
            return null;
        }

        $reservationId = 'RSV-' . strtoupper(substr(md5(uniqid()), 0, 8));

        $reservation = [
            'reservation_id' => $reservationId,
            'attraction_id' => $attractionId,
            'attraction_name' => $attraction['name'],
            'category' => $attraction['category'],
            'number_of_people' => $numberOfPeople,
            'reservation_date' => $reservationDate,
            'reservation_time' => $reservationTime,
            'guest_name' => $guestName,
            'guest_email' => $guestEmail,
            'special_requests' => $specialRequests,
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'confirmed_at' => null,
            'opening_hours' => $attraction['opening_hours'],
            'location' => [
                'latitude' => $attraction['latitude'],
                'longitude' => $attraction['longitude'],
            ],
        ];

        // Store reservation in cache for 24 hours
        Cache::put(self::RESERVATION_CACHE_PREFIX . $reservationId, $reservation, self::RESERVATION_TTL);
        
        // Add to reservation index
        $this->addToReservationIndex($reservationId);

        Log::info('Restaurant reservation prepared and cached', [
            'reservation_id' => $reservationId, 
            'restaurant' => $attraction['name'],
            'guest' => $guestName,
        ]);

        return $reservation;
    }

    /**
     * Confirm a restaurant reservation
     */
    public function confirmRestaurantReservation(string $reservationId): ?array
    {
        $reservation = Cache::get(self::RESERVATION_CACHE_PREFIX . $reservationId);
        
        if (!$reservation) {
            return null;
        }

        $reservation['status'] = 'confirmed';
        $reservation['confirmed_at'] = date('Y-m-d H:i:s');
        
        // Generate confirmation number
        $reservation['confirmation_number'] = 'CNF-' . strtoupper(substr(md5($reservationId), 0, 10));

        // Update reservation in cache
        Cache::put(self::RESERVATION_CACHE_PREFIX . $reservationId, $reservation, self::RESERVATION_TTL);

        Log::info('Restaurant reservation confirmed and cached', ['reservation_id' => $reservationId]);

        return $reservation;
    }

    /**
     * Get reservation by ID
     */
    public function getReservation(string $reservationId): ?array
    {
        return Cache::get(self::RESERVATION_CACHE_PREFIX . $reservationId);
    }

    /**
     * Cancel a restaurant reservation
     */
    public function cancelReservation(string $reservationId): bool
    {
        $reservation = Cache::get(self::RESERVATION_CACHE_PREFIX . $reservationId);
        
        if (!$reservation) {
            return false;
        }

        $reservation['status'] = 'cancelled';
        $reservation['cancelled_at'] = date('Y-m-d H:i:s');

        // Update reservation in cache
        Cache::put(self::RESERVATION_CACHE_PREFIX . $reservationId, $reservation, self::RESERVATION_TTL);

        Log::info('Restaurant reservation cancelled and cached', ['reservation_id' => $reservationId]);

        return true;
    }

    /**
     * Get all restaurant reservations
     */
    public function getAllReservations(): array
    {
        $reservationIds = Cache::get(self::RESERVATION_INDEX_KEY, []);
        $reservations = [];
        
        foreach ($reservationIds as $reservationId) {
            $reservation = Cache::get(self::RESERVATION_CACHE_PREFIX . $reservationId);
            if ($reservation) {
                $reservations[] = $reservation;
            }
        }
        
        return $reservations;
    }

    /**
     * Add reservation ID to index
     */
    private function addToReservationIndex(string $reservationId): void
    {
        $reservationIds = Cache::get(self::RESERVATION_INDEX_KEY, []);
        
        if (!in_array($reservationId, $reservationIds)) {
            $reservationIds[] = $reservationId;
            Cache::put(self::RESERVATION_INDEX_KEY, $reservationIds, self::RESERVATION_TTL);
        }
    }

    /**
     * Save or update user profile
     */
    public function saveUserProfile(array $userProfile): void
    {
        $userId = $userProfile['user_id'];
        Cache::put(self::USER_PROFILE_CACHE_PREFIX . $userId, $userProfile, self::USER_PROFILE_TTL);
        
        Log::info('User profile saved', ['user_id' => $userId]);
    }

    /**
     * Get user profile
     */
    public function getUserProfile(string $userId): ?array
    {
        return Cache::get(self::USER_PROFILE_CACHE_PREFIX . $userId);
    }

    /**
     * Get personalized attraction recommendations
     */
    public function getRecommendedAttractions(int $destinationId, array $userProfile, int $limit = 6): array
    {
        // Get all attractions for the destination
        $attractions = array_filter($this->attractions, function ($attraction) use ($destinationId) {
            return $attraction['destination_id'] === $destinationId;
        });

        if (empty($attractions)) {
            return [];
        }

        // Score each attraction based on user profile
        $scoredAttractions = [];
        foreach ($attractions as $attraction) {
            $score = $this->calculateMatchScore($attraction, $userProfile);
            $matchedTags = $this->getMatchedTags($attraction, $userProfile);
            
            $scoredAttractions[] = array_merge($attraction, [
                'match_score' => $score,
                'matched_tags' => $matchedTags,
            ]);
        }

        // Sort by score (highest first)
        usort($scoredAttractions, function ($a, $b) {
            return $b['match_score'] <=> $a['match_score'];
        });

        // Return top N attractions
        return array_slice($scoredAttractions, 0, $limit);
    }

    /**
     * Calculate match score for an attraction based on user profile
     */
    private function calculateMatchScore(array $attraction, array $userProfile): int
    {
        $score = 50; // Base score
        $attractionTags = $attraction['tags'] ?? [];
        
        // Preference matching (40 points max)
        $preferences = $userProfile['preferences'] ?? [];
        if (!empty($preferences)) {
            $matchingPreferences = array_intersect($attractionTags, $preferences);
            $preferenceScore = (count($matchingPreferences) / count($preferences)) * 40;
            $score += $preferenceScore;
        }

        // Travel type matching (20 points max)
        $travelType = $userProfile['travel_type'] ?? 'general';
        if ($travelType !== 'general') {
            $travelTypeMap = [
                'solo' => ['budget', 'culture', 'photography', 'adventure'],
                'family' => ['family-friendly', 'budget', 'outdoor'],
                'romantic' => ['romantic', 'luxury', 'photography'],
                'business' => ['culture', 'architecture'],
                'adventure' => ['adventure', 'outdoor', 'sports', 'nature'],
                'cultural' => ['culture', 'history', 'art', 'architecture', 'music'],
                'budget' => ['budget'],
                'luxury' => ['luxury', 'romantic'],
            ];
            
            $expectedTags = $travelTypeMap[$travelType] ?? [];
            $matchingTravelTags = array_intersect($attractionTags, $expectedTags);
            if (!empty($expectedTags)) {
                $travelScore = (count($matchingTravelTags) / count($expectedTags)) * 20;
                $score += $travelScore;
            }
        }

        // Age group matching (10 points max)
        $ageGroup = $userProfile['age_group'] ?? 'adult';
        $ageGroupMap = [
            'child' => ['family-friendly', 'adventure', 'outdoor'],
            'teen' => ['adventure', 'sports', 'outdoor', 'photography'],
            'family' => ['family-friendly', 'budget'],
            'senior' => ['culture', 'history', 'art', 'architecture'],
        ];
        
        if (isset($ageGroupMap[$ageGroup])) {
            $expectedAgeTags = $ageGroupMap[$ageGroup];
            $matchingAgeTags = array_intersect($attractionTags, $expectedAgeTags);
            if (!empty($expectedAgeTags)) {
                $ageScore = (count($matchingAgeTags) / count($expectedAgeTags)) * 10;
                $score += $ageScore;
            }
        }

        // Budget matching (10 points max)
        $budget = $userProfile['budget'] ?? 'moderate';
        if ($budget === 'budget' && in_array('budget', $attractionTags)) {
            $score += 10;
        } elseif ($budget === 'luxury' && in_array('luxury', $attractionTags)) {
            $score += 10;
        } elseif ($budget === 'moderate') {
            $score += 5; // Moderate budget gets partial points
        }

        // Cap at 100
        return min(100, (int) $score);
    }

    /**
     * Get matched tags between attraction and user preferences
     */
    private function getMatchedTags(array $attraction, array $userProfile): array
    {
        $attractionTags = $attraction['tags'] ?? [];
        $preferences = $userProfile['preferences'] ?? [];
        
        if (empty($preferences)) {
            return [];
        }

        $matchedTags = array_intersect($attractionTags, $preferences);
        
        // Return human-readable matched tags
        return array_map(function ($tag) {
            return ucfirst(str_replace('-', ' ', $tag));
        }, array_values($matchedTags));
    }

    /**
     * Haversine formula
     */
    private function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371; // km

        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }
}
