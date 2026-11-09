<?php

namespace App\Services\Payments;

use App\Services\Payments\Drivers\CryptoGateway;
use App\Services\Payments\Drivers\PayPalGateway;
use App\Services\Payments\Drivers\RevolutGateway;
use App\Services\Payments\Drivers\StripeGateway;
use Illuminate\Support\Manager;

/**
 * Resolves a PaymentGateway driver by name (config('payments.default') or
 * an explicit gateway key). Adding a provider is a new createXDriver()
 * method plus a config('payments.gateways.x') entry — nothing else in the
 * system changes.
 */
class PaymentManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return $this->container->make('config')->get('payments.default');
    }

    protected function createStripeDriver(): StripeGateway
    {
        return new StripeGateway($this->container->make('config')->get('payments.gateways.stripe', []));
    }

    protected function createPaypalDriver(): PayPalGateway
    {
        return new PayPalGateway($this->container->make('config')->get('payments.gateways.paypal', []));
    }

    protected function createCryptoDriver(): CryptoGateway
    {
        return new CryptoGateway($this->container->make('config')->get('payments.gateways.crypto', []));
    }

    protected function createRevolutDriver(): RevolutGateway
    {
        return new RevolutGateway($this->container->make('config')->get('payments.gateways.revolut', []));
    }
}
