<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Mastercard Payment Gateway Services
    |--------------------------------------------------------------------------
    */

    'mastercard' => [
        'api_url' => env('MASTERCARD_API_URL'),
        'consumer_key' => env('MASTERCARD_CONSUMER_KEY'),
        'private_key' => env('MASTERCARD_PRIVATE_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Hotel Room Availability Service (CapCorn)
    |--------------------------------------------------------------------------
    |
    | Configuration for the CapCorn room availability endpoint used for hotel
    | room searches. Defaults point to hackathon credentials but should be
    | overridden via environment variables in production.
    */

    'hotel_availability' => [
        'endpoint' => env('HOTEL_AVAILABILITY_ENDPOINT', 'https://mainframe.capcorn.net/RestService/RoomAvailability'),
        'system' => env('HOTEL_AVAILABILITY_SYSTEM', 'ttf-hackathon'),
        'user' => env('HOTEL_AVAILABILITY_USER', 'ttf'),
        'password' => env('HOTEL_AVAILABILITY_PASSWORD', 'Uv9-k_gYbmcsTHyU'),
        'default_hotel_id' => env('HOTEL_AVAILABILITY_DEFAULT_HOTEL_ID', 9100),
        'default_language' => env('HOTEL_AVAILABILITY_DEFAULT_LANGUAGE', 1),
        'reservation_endpoint' => env('HOTEL_RESERVATION_ENDPOINT', 'https://mainframe.capcorn.net/RestService/OTA_HotelResNotifRQ'),
        'reservation_pin' => env('HOTEL_RESERVATION_PIN', 'Uv9-k_gYbmcsTHyU'),
    ],

    /*
    |--------------------------------------------------------------------------
    | DSAPI Kärnten Experience Booking
    |--------------------------------------------------------------------------
    |
    | Configuration for the DSAPI service for booking experiences in Kärnten.
    | Authentication is handled automatically and tokens are cached for 8 hours.
    */

    'dsapi' => [
        'base_url' => env('DSAPI_BASE_URL', 'https://dsapi.deskline.net'),
        'region' => env('DSAPI_REGION', 'kaernten'),
        'db_code' => env('DSAPI_DB_CODE', 'KTN'),
        'theme_limit' => env('DSAPI_THEME_LIMIT', '38723CC4-C5F0-4707-9401-5F598D892246'),
        'username' => env('DSAPI_USERNAME'),
        'password' => env('DSAPI_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | CapCorn API
    |--------------------------------------------------------------------------
    |
    | Base URL for the CapCorn API (hotel availability, direct availability,
    | and reservation endpoints exposed by our CapCornServer tools).
    |
    */
    'capcorn' => [
        'base_url' => env('CAPCORN_BASE_URL', 'https://lookingcom-backend.vercel.app'),
    ],

];
