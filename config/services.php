<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Google (Socialite)
    |--------------------------------------------------------------------------
    |
    | Farmer-only "Sign in with Google" (App\Http\Controllers\Auth\
    | GoogleController). Create these in Google Cloud Console -> APIs &
    | Services -> Credentials -> Create Credentials -> OAuth client ID
    | (Application type: Web application), then add GOOGLE_CLIENT_ID,
    | GOOGLE_CLIENT_SECRET and GOOGLE_REDIRECT_URI to .env - this file never
    | holds the actual values. The redirect URI must be added to the OAuth
    | client's "Authorized redirect URIs" in Google Cloud Console exactly as
    | it is set here, e.g. http://localhost:8000/auth/google/callback for
    | local dev, or https://yourdomain/auth/google/callback in production.
    |
    */

    'google' => [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect'      => env('GOOGLE_REDIRECT_URI'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
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
    | Semaphore (SMS)
    |--------------------------------------------------------------------------
    |
    | Semaphore (semaphore.co) is a Philippine SMS gateway billed in pesos,
    | which is a better fit for this office's procurement than a USD-billed
    | international provider. See App\Services\SemaphoreSmsService. Add
    | SEMAPHORE_API_KEY (and, once a sender name is registered with
    | Semaphore, SEMAPHORE_SENDER_NAME) to .env - this file never holds the
    | actual key.
    |
    */

    'semaphore' => [
        'api_key'     => env('SEMAPHORE_API_KEY'),
        'sender_name' => env('SEMAPHORE_SENDER_NAME', 'Semaphore'),
    ],

];
