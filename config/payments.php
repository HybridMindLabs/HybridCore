<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway
    |--------------------------------------------------------------------------
    */

    'default' => env('PAYMENTS_DEFAULT_GATEWAY', 'stripe'),

    /*
    |--------------------------------------------------------------------------
    | Payment Gateways
    |--------------------------------------------------------------------------
    |
    | Each gateway is a driver PaymentManager knows how to build. Adding a
    | new gateway is a new key here plus a new create{Name}Driver() method
    | on PaymentManager — nothing else in the system changes.
    |
    */

    'gateways' => [
        'stripe' => [
            'name' => 'Card / Stripe',
            'driver' => 'stripe',
            'enabled' => env('PAYMENTS_STRIPE_ENABLED', true),
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
        'paypal' => [
            'name' => 'PayPal',
            'driver' => 'paypal',
            'enabled' => env('PAYMENTS_PAYPAL_ENABLED', true),
            'client_id' => env('PAYPAL_CLIENT_ID'),
            'client_secret' => env('PAYPAL_CLIENT_SECRET'),
            'mode' => env('PAYPAL_MODE', 'sandbox'),
            'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
        ],
        'crypto' => [
            'name' => 'Cryptocurrency',
            'driver' => 'crypto',
            'enabled' => env('PAYMENTS_CRYPTO_ENABLED', true),
            'provider' => env('CRYPTO_PROVIDER', 'coinbase'),
            'api_key' => env('CRYPTO_API_KEY'),
            'webhook_secret' => env('CRYPTO_WEBHOOK_SECRET'),
        ],
        'revolut' => [
            'name' => 'Revolut Pay',
            'driver' => 'revolut',
            'enabled' => env('PAYMENTS_REVOLUT_ENABLED', true),
            'secret_key' => env('REVOLUT_SECRET_KEY'),
            'webhook_secret' => env('REVOLUT_WEBHOOK_SECRET'),
            'mode' => env('REVOLUT_MODE', 'sandbox'),
        ],
    ],

];
