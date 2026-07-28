<?php

namespace eRede\Tests;

use eRede\Providers\eRedeServiceProvider;
use Illuminate\Contracts\Config\Repository;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [eRedeServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        tap($app->make('config'), function (Repository $config): void {
            $config->set('cache.default', 'array');
            $config->set('erede.pv', 'pv-de-teste');
            $config->set('erede.token', 'token-de-teste');
            $config->set('erede.mode', 'sandbox');
        });
    }

    /**
     * Payload de sucesso do endpoint OAuth da Rede.
     *
     * @return array<string,mixed>
     */
    protected function oauthPayload(int $expiresIn = 3600): array
    {
        return [
            'access_token' => 'token-oauth-fake',
            'token_type' => 'Bearer',
            'expires_in' => $expiresIn,
            'scope' => 'transactions',
        ];
    }
}
