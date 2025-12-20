<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as OAuth providers, payment gateways, etc.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Supported OAuth Providers
    |--------------------------------------------------------------------------
    */
    'oauth_providers' => array_filter(explode(',', env('OAUTH_PROVIDERS', 'google,github'))),

    /*
    |--------------------------------------------------------------------------
    | Google OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL') . '/auth/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | GitHub OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL') . '/auth/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Microsoft OAuth Configuration
    |--------------------------------------------------------------------------
    */
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL') . '/auth/callback'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Generic OAuth/OIDC Configuration
    |--------------------------------------------------------------------------
    */
    'oidc' => [
        'client_id' => env('OIDC_CLIENT_ID'),
        'client_secret' => env('OIDC_CLIENT_SECRET'),
        'redirect' => env('OIDC_REDIRECT_URI', env('APP_URL') . '/auth/callback'),
        'authorize_url' => env('OIDC_AUTHORIZE_URL'),
        'token_url' => env('OIDC_TOKEN_URL'),
        'userinfo_url' => env('OIDC_USERINFO_URL'),
    ],

];
