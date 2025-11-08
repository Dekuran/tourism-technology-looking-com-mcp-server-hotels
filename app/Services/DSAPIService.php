<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class DSAPIService
{
    private string $baseUrl;
    private string $region;
    private string $dbCode;
    private string $themeLimit;
    private ?string $bearerToken = null;

    public function __construct()
    {
        $this->baseUrl = config('services.dsapi.base_url');
        $this->region = config('services.dsapi.region');
        $this->dbCode = config('services.dsapi.db_code');
        $this->themeLimit = config('services.dsapi.theme_limit');
        
        $this->ensureAuthenticated();
    }

    private function ensureAuthenticated(): void
    {
        $username = config('services.dsapi.username');
        $password = config('services.dsapi.password');

        if (!$username || !$password) {
            throw new \RuntimeException('DSAPI credentials not configured. Set DSAPI_USERNAME and DSAPI_PASSWORD in .env');
        }

        $cacheKey = "dsapi_token_{$username}";
        
        if ($token = Cache::get($cacheKey)) {
            $this->bearerToken = $token;
            return;
        }

        // DSAPI uses query parameters for authentication, not JSON body
        $url = $this->baseUrl . '/Auth?' . http_build_query([
            'username' => $username,
            'password' => $password,
        ]);
        
        $response = Http::post($url);

        if (!$response->successful()) {
            throw new \RuntimeException('DSAPI authentication failed: ' . $response->body());
        }

        $token = $response->json('token') ?? $response->body();
        $this->bearerToken = $token;
        
        Cache::put($cacheKey, $token, now()->addHours(8));
    }

    public function createSearch(string $dateFrom, string $dateTo): array
    {
        $response = $this->makeRequest('POST', '/searches', [
            'searchObject' => [
                'searchGeneral' => [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ],
            ],
        ]);

        return $response;
    }

    public function updateSearch(string $searchId, string $dateFrom, string $dateTo): array
    {
        $response = $this->makeRequest('PUT', "/searches/{$searchId}", [
            'searchObject' => [
                'id' => $searchId,
                'searchGeneral' => [
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                ],
            ],
        ]);

        return $response;
    }

    public function createFilter(
        ?array $types = null,
        ?array $locations = null,
        ?array $holidayThemes = null,
        ?array $guestCards = null,
        ?string $name = ''
    ): array {
        $response = $this->makeRequest('POST', '/filters', [
            'filterObject' => [
                'id' => '00000000-0000-0000-0000-000000000000',
                'filterGeneral' => new \stdClass(),
                'filterAddServices' => [
                    'types' => $types,
                    'holidayThemes' => $holidayThemes,
                    'locations' => $locations,
                    'guestCards' => $guestCards,
                    'name' => $name ?? '',
                ],
            ],
        ]);

        return $response;
    }

    public function updateFilter(
        string $filterId,
        ?array $types = null,
        ?array $locations = null,
        ?array $holidayThemes = null,
        ?array $guestCards = null,
        ?string $name = ''
    ): array {
        $response = $this->makeRequest('PUT', "/filters/{$filterId}", [
            'filterObject' => [
                'id' => $filterId,
                'filterGeneral' => new \stdClass(),
                'filterAddServices' => [
                    'types' => $types,
                    'holidayThemes' => $holidayThemes,
                    'locations' => $locations,
                    'guestCards' => $guestCards,
                    'name' => $name ?? '',
                ],
            ],
        ]);

        return $response;
    }

    public function getFilterOptions(string $filterId, string $language = 'de'): array
    {
        $fields = [
            'types{id,name,count}',
            'holidayThemes{id,name,count}',
            'locations(locTypes:[3]){id,name,count}',
            'guestCards{id,name,count,type,typeId,iconUrl,webLink}',
        ];

        $response = $this->makeRequest('GET', "/addservices/{$this->region}/{$language}/filterresults/{$filterId}", [], [
            'fields' => implode(',', $fields),
            'limAddSrvTHEME' => $this->themeLimit,
            'limExAccShSPwoPr' => 'false',
        ]);

        return $response;
    }

    public function listExperiences(
        string $filterId,
        string $language = 'de',
        string $currency = 'EUR',
        int $pageNo = 0,
        int $pageSize = 5000
    ): array {
        $response = $this->makeRequest('GET', "/addservices/{$this->region}/{$language}/", [], [
            'filterId' => $filterId,
            'currency' => $currency,
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ]);

        return $response;
    }

    public function searchExperiences(
        string $searchId,
        string $filterId,
        string $language = 'de',
        string $currency = 'EUR',
        int $pageNo = 1,
        int $pageSize = 5000
    ): array {
        $response = $this->makeRequest('GET', "/addservices/{$this->region}/{$language}/searchresults/{$searchId}", [], [
            'filterId' => $filterId,
            'currency' => $currency,
            'pageNo' => $pageNo,
            'pageSize' => $pageSize,
        ]);

        return $response;
    }

    public function getServiceProducts(
        string $spIdentity,
        string $serviceId,
        string $filterId,
        string $language = 'de',
        string $currency = 'EUR'
    ): array {
        $fields = 'id,name,isFreeBookable,price{from,to,insteadFrom,insteadTo}';
        
        $response = $this->makeRequest(
            'GET',
            "/addservices/{$this->region}/{$language}/{$this->dbCode}/{$spIdentity}/services/{$serviceId}/products",
            [],
            [
                'fields' => $fields,
                'currency' => $currency,
                'limAddSrvTHEME' => $this->themeLimit,
                'limExAccShSPwoPr' => 'false',
                'filterId' => $filterId,
            ]
        );

        return $response;
    }

    public function getProductAvailability(
        string $spIdentity,
        string $serviceId,
        string $searchId,
        string $filterId,
        string $language = 'de',
        string $currency = 'EUR'
    ): array {
        $fields = 'id,name,isFreeBookable,isOwnAvailability,' .
                  'priceChoosableByGuest{active,minPrice,maxPrice},' .
                  'bookInfo{date,startTime,duration,price,insteadPrice,availability,' .
                  'isBookable,isBookableOnRequest,isOfferable,' .
                  'paymentCancellationPolicy{cancellationPolicy{' .
                  'cancellationTextType,defaultHeaderTextNumber,hasFreeCancellation,' .
                  'lastFreeDate,lastFreeTime,' .
                  'textLines{cancellationCalculationType,cancellationNights,cancellationPercentage,' .
                  'defaultTextNumber,hasFreeTime,freeTime,cancellationDate}}}}';
        
        $response = $this->makeRequest(
            'GET',
            "/addservices/{$this->region}/{$language}/{$this->dbCode}/{$spIdentity}/services/{$serviceId}/searchresults/{$searchId}",
            [],
            [
                'filterId' => $filterId,
                'fields' => $fields,
                'currency' => $currency,
                'limAddSrvTHEME' => $this->themeLimit,
                'limExAccShSPwoPr' => 'false',
            ]
        );

        return $response;
    }

    public function createShoppingList(): array
    {
        $response = $this->makeRequest('POST', '/shoppinglist/' . $this->region);
        
        return $response;
    }

    public function addToShoppingList(
        string $shoppingListId,
        array $addServiceItems = [],
        array $accommodationItems = [],
        array $brochureItems = [],
        array $packageItems = [],
        array $tourItems = []
    ): array {
        $response = $this->makeRequest('POST', "/shoppinglist/{$this->region}/{$shoppingListId}/items/add", [
            'addServiceItems' => $addServiceItems,
            'accommodationItems' => $accommodationItems,
            'brochureItems' => $brochureItems,
            'packageItems' => $packageItems,
            'tourItems' => $tourItems,
        ]);

        return $response;
    }

    private function makeRequest(string $method, string $endpoint, array $body = [], array $queryParams = []): array
    {
        $url = $this->baseUrl . $endpoint;
        
        // DSAPI requires both Authorization Bearer and DW-SessionId headers
        $request = Http::timeout(30)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'DW-SessionId' => $this->bearerToken,
                'Content-Type' => 'application/json',
            ]);
        
        $response = match ($method) {
            'GET' => $request->get($url, $queryParams),
            'POST' => empty($queryParams) ? $request->post($url, $body) : $request->post($url . '?' . http_build_query($queryParams), $body),
            'PUT' => $request->put($url, $body),
            'DELETE' => $request->delete($url, $body),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}"),
        };

        if (!$response->successful()) {
            return [
                'success' => false,
                'error' => $response->body(),
                'status' => $response->status(),
            ];
        }

        $data = $response->json();
        
        return [
            'success' => true,
            'data' => $data,
        ];
    }
}

