<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class MastercardService
{
    private string $consumerKey;
    private string $privateKey;
    private string $apiUrl;

    public function __construct()
    {
        $this->consumerKey = config('services.mastercard.consumer_key');
        $this->privateKey = config('services.mastercard.private_key');
        $this->apiUrl = config('services.mastercard.api_url');
    }

    /**
     * Search for ATM locations using v2 API (POST with JSON body)
     */
    public function searchATMs(array $params): array
    {
        $endpoint = "/locations/atms/searches";
        
        // Build query parameters (limit, offset, distance, distance_unit)
        $queryParams = [];
        if (isset($params['PageLength'])) {
            $queryParams['limit'] = $params['PageLength'];
        }
        if (isset($params['PageOffset'])) {
            $queryParams['offset'] = $params['PageOffset'];
        }
        if (isset($params['Distance'])) {
            $queryParams['distance'] = $params['Distance'];
        }
        if (isset($params['DistanceUnit'])) {
            $queryParams['distance_unit'] = $params['DistanceUnit'];
        }
        
        // Build request body
        $body = [];
        
        // Add location parameters
        if (isset($params['Latitude']) && isset($params['Longitude'])) {
            $body['latitude'] = (string) $params['Latitude'];
            $body['longitude'] = (string) $params['Longitude'];
        }
        
        if (isset($params['PostalCode'])) {
            $body['postalCode'] = $params['PostalCode'];
        }
        
        if (isset($params['Country'])) {
            $body['countryCode'] = $params['Country'];
        }
        
        // Optional: Add city if available (from search params)
        if (isset($params['City'])) {
            $body['city'] = $params['City'];
        }

        return $this->makeAuthenticatedRequest('POST', $endpoint, $queryParams, $body);
    }

    /**
     * Make authenticated API request to Mastercard
     */
    public function makeAuthenticatedRequest(string $method, string $endpoint, array $queryParams = [], array $bodyParams = []): array
    {
        try {
            // Build URL
            $url = $this->apiUrl . $endpoint;
            if (!empty($queryParams)) {
                $url .= '?' . http_build_query($queryParams);
            }

            // Convert body to JSON for signature
            $bodyJson = !empty($bodyParams) ? json_encode($bodyParams) : null;

            // Generate OAuth signature with body hash
            $oauthParams = $this->generateOAuthSignature($url, $method, $queryParams, $bodyJson);

            // Make API request
            $result = $this->makeApiRequest($url, $method, $oauthParams, $bodyParams, $bodyJson);

            if ($result['status'] == 200 && isset($result['body'])) {
                return [
                    'success' => true,
                    'data' => $result['body'],
                    'status' => $result['status'],
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API_ERROR',
                    'message' => 'Failed to retrieve data from Mastercard',
                    'status' => $result['status'],
                    'body' => $result['body'] ?? null,
                ];
            }

        } catch (\Exception $e) {
            Log::error('Mastercard API request failed', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'EXCEPTION',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Generate OAuth 1.0a signature with RSA-SHA256
     */
    private function generateOAuthSignature(string $url, string $method, array $queryParams = [], ?string $bodyJson = null): array
    {
        $oauthParams = [
            'oauth_consumer_key' => $this->consumerKey,
            'oauth_nonce' => $this->generateNonce(),
            'oauth_signature_method' => 'RSA-SHA256',
            'oauth_timestamp' => time(),
            'oauth_version' => '1.0',
        ];

        // Add body hash for POST/PUT requests with body
        if (in_array($method, ['POST', 'PUT', 'PATCH']) && !empty($bodyJson)) {
            $oauthParams['oauth_body_hash'] = $this->generateBodyHash($bodyJson);
        }

        $signatureBaseString = $this->createSignatureBaseString($method, $url, $oauthParams, $queryParams);
        $signature = $this->signWithRSA($signatureBaseString);
        $oauthParams['oauth_signature'] = $signature;

        return $oauthParams;
    }

    /**
     * Generate OAuth body hash (Base64(SHA-256(body)))
     */
    private function generateBodyHash(string $body): string
    {
        return base64_encode(hash('sha256', $body, true));
    }

    /**
     * Create OAuth signature base string
     */
    private function createSignatureBaseString(string $method, string $url, array $oauthParams, array $queryParams = []): string
    {
        $urlParts = parse_url($url);
        $baseUrl = $urlParts['scheme'] . '://' . $urlParts['host'];
        
        if (isset($urlParts['port']) && $urlParts['port'] != 443 && $urlParts['port'] != 80) {
            $baseUrl .= ':' . $urlParts['port'];
        }
        
        $baseUrl .= $urlParts['path'] ?? '/';

        // Merge all parameters
        $allParams = array_merge($oauthParams, $queryParams);
        ksort($allParams);

        $paramString = http_build_query($allParams, '', '&', PHP_QUERY_RFC3986);

        return strtoupper($method) . '&' . rawurlencode($baseUrl) . '&' . rawurlencode($paramString);
    }

    /**
     * Sign the base string with RSA-SHA256
     */
    private function signWithRSA(string $baseString): string
    {
        $key = openssl_pkey_get_private($this->privateKey);
        
        if ($key === false) {
            throw new \Exception('Invalid private key: ' . openssl_error_string());
        }

        $signature = '';
        $result = openssl_sign($baseString, $signature, $key, OPENSSL_ALGO_SHA256);
        openssl_free_key($key);

        if (!$result) {
            throw new \Exception('Failed to sign request: ' . openssl_error_string());
        }

        return base64_encode($signature);
    }

    /**
     * Generate a random nonce
     */
    private function generateNonce(): string
    {
        return bin2hex(random_bytes(16));
    }

    /**
     * Make HTTP API request with OAuth authentication
     */
    private function makeApiRequest(string $url, string $method, array $oauthParams, array $bodyParams = [], ?string $bodyJson = null): array
    {
        // Build OAuth Authorization header
        $headerParams = [];
        foreach ($oauthParams as $key => $value) {
            $headerParams[] = rawurlencode($key) . '="' . rawurlencode($value) . '"';
        }
        $authHeader = 'OAuth ' . implode(', ', $headerParams);

        $client = new Client([
            'verify' => true,
            'timeout' => 30,
        ]);
        
        try {
            $options = [
                'headers' => [
                    'Authorization' => $authHeader,
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ];

            // Add body for POST/PUT requests
            if (!empty($bodyJson) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $options['body'] = $bodyJson;
            }

            $response = $client->request($method, $url, $options);

            $rawBody = $response->getBody()->getContents();
            $contentType = $response->getHeader('Content-Type')[0] ?? '';
            
            // Parse based on content type
            if (str_contains($contentType, 'xml')) {
                $decodedBody = $this->parseXmlResponse($rawBody);
            } elseif (str_contains($contentType, 'json')) {
                $decodedBody = json_decode($rawBody, true);
            } else {
                // Try JSON first, fallback to XML
                $decodedBody = json_decode($rawBody, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $decodedBody = $this->parseXmlResponse($rawBody);
                }
            }

            return [
                'status' => $response->getStatusCode(),
                'body' => $decodedBody,
                'headers' => $response->getHeaders(),
            ];

        } catch (RequestException $e) {
            $statusCode = $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0;
            $body = $e->hasResponse() ? $e->getResponse()->getBody()->getContents() : $e->getMessage();
            
            Log::error('Mastercard API request exception', [
                'url' => $url,
                'status' => $statusCode,
                'error' => $body,
            ]);

            return [
                'status' => $statusCode,
                'body' => $body,
            ];
        }
    }

    /**
     * Parse XML response to array
     */
    public function parseXmlResponse(string $xmlString): array
    {
        try {
            // Suppress XML errors and handle them manually
            libxml_use_internal_errors(true);
            
            $xml = simplexml_load_string($xmlString);
            
            if ($xml === false) {
                $errors = libxml_get_errors();
                libxml_clear_errors();
                
                Log::error('XML parsing failed', [
                    'errors' => $errors,
                    'xml' => substr($xmlString, 0, 500),
                ]);
                
                return [];
            }

            // Parse ATM response
            if (isset($xml->Atm)) {
                return $this->parseATMsXml($xml);
            }

            // Generic XML to array conversion
            return $this->xmlToArray($xml);

        } catch (\Exception $e) {
            Log::error('XML parsing exception', [
                'error' => $e->getMessage(),
            ]);
            
            return [];
        }
    }

    /**
     * Parse ATMs XML response
     */
    private function parseATMsXml(\SimpleXMLElement $xml): array
    {
        $result = [
            'PageOffset' => (string) ($xml->PageOffset ?? '0'),
            'TotalCount' => (string) ($xml->TotalCount ?? '0'),
            'Atms' => ['Atm' => []]
        ];

        foreach ($xml->Atm as $atm) {
            $result['Atms']['Atm'][] = [
                'Location' => [
                    'Name' => (string) $atm->Location->Name,
                    'Address' => [
                        'Line1' => (string) $atm->Location->Address->Line1,
                        'Line2' => (string) $atm->Location->Address->Line2,
                        'City' => (string) $atm->Location->Address->City,
                        'PostalCode' => (string) $atm->Location->Address->PostalCode,
                        'CountrySubdivision' => [
                            'Name' => (string) $atm->Location->Address->CountrySubdivision->Name,
                            'Code' => (string) $atm->Location->Address->CountrySubdivision->Code,
                        ],
                        'Country' => [
                            'Name' => (string) $atm->Location->Address->Country->Name,
                            'Code' => (string) $atm->Location->Address->Country->Code,
                        ]
                    ],
                    'Geocode' => [
                        'Latitude' => (string) $atm->Location->Point->Latitude,
                        'Longitude' => (string) $atm->Location->Point->Longitude,
                    ],
                    'LocationType' => isset($atm->Location->LocationType->Type) 
                        ? (string) $atm->Location->LocationType->Type 
                        : null,
                ],
                'Distance' => (string) $atm->Location->Distance,
                'DistanceUnit' => (string) $atm->Location->DistanceUnit,
                'HandicapAccessible' => (string) $atm->HandicapAccessible,
                'Camera' => (string) $atm->Camera,
                'Availability' => (string) $atm->Availability,
                'AccessFees' => (string) $atm->AccessFees,
                'Owner' => (string) $atm->Owner,
                'SharedDeposit' => (string) $atm->SharedDeposit,
                'Sponsor' => (string) $atm->Sponsor,
                'SupportEMV' => (string) $atm->SupportEMV,
            ];
        }

        return $result;
    }

    /**
     * Generic XML to array conversion
     */
    private function xmlToArray(\SimpleXMLElement $xml): array
    {
        $json = json_encode($xml);
        return json_decode($json, true);
    }

    /**
     * Check if Mastercard API is configured
     */
    public function isConfigured(): bool
    {
        return !empty($this->consumerKey) && 
               !empty($this->privateKey) && 
               !empty($this->apiUrl);
    }

    /**
     * Get configuration status
     */
    public function getConfigStatus(): array
    {
        return [
            'configured' => $this->isConfigured(),
            'consumer_key_set' => !empty($this->consumerKey),
            'private_key_set' => !empty($this->privateKey),
            'api_url_set' => !empty($this->apiUrl),
            'api_url' => $this->apiUrl,
        ];
    }
}

