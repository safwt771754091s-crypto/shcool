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
    | SMS gateway
    |--------------------------------------------------------------------------
    | A generic HTTP SMS provider (Twilio-compatible). Leave the credentials
    | empty to disable the channel; the platform then records messages as
    | queued instead of sending them.
    */
    'sms' => [
        'endpoint' => env('SMS_ENDPOINT'),
        'username' => env('SMS_USERNAME'),
        'password' => env('SMS_PASSWORD'),
        'sender' => env('SMS_SENDER'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Business Cloud API
    |--------------------------------------------------------------------------
    | Meta Graph API credentials. Without a token and phone number id the
    | WhatsApp channel stays unavailable and is skipped automatically.
    */
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v20.0'),
        'base_url' => env('WHATSAPP_BASE_URL', 'https://graph.facebook.com'),
    ],

];
