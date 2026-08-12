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
            'driver' => 'stripe',
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
