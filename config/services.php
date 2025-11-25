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

    'sso' => [
        'slo_key'  => env('SSO_SLO_SECRET'),
        'client_id'     => env('SSO_CLIENT_ID'),
        'client_secret' => env('SSO_CLIENT_SECRET'),
        
        // Endpoint del Server IdP
        'redirect_uri'  => env('SSO_REDIRECT_URI'),

        // Server SSO Endpoints
        'dashboard'     => env('SSO_DASHBOARD'),
        'auth_url'      => env('SSO_AUTH_URL'), // Ora config('services.sso.auth_url') funzionerà
        'token_url'     => env('SSO_TOKEN_URL'),
        'userinfo_url'  => env('SSO_USERINFO_URL'),
        'scope'         => env('SSO_SCOPE'),
    ],

];
