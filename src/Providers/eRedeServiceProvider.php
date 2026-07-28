<?php

namespace eRede\Providers;

use eRede\eRede;
use eRede\Support\Config;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Support\ServiceProvider;

class eRedeServiceProvider extends ServiceProvider
{
    /** Caminho do config publicável do pacote. */
    private const CONFIG_PATH = __DIR__.'/../../config/erede.php';

    public function register(): void
    {
        $this->mergeConfigFrom(self::CONFIG_PATH, 'erede');

        $this->registerLogChannel();

        $this->app->singleton(Config::class, function ($app): Config {
            /** @var ConfigRepository $config */
            $config = $app->make('config');

            return Config::fromArray($config->get('erede', []));
        });

        $this->app->singleton(eRede::class, fn ($app): eRede => new eRede(
            config: $app->make(Config::class),
            container: $app,
        ));

        $this->app->alias(eRede::class, 'erede');
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                self::CONFIG_PATH => $this->app->configPath('erede.php'),
            ], 'erede-config');
        }
    }

    /**
     * Registra o canal de log do pacote em logging.channels.
     *
     * Se a aplicação já definiu um canal com esse nome, a definição dela vence:
     * o pacote nunca sobrescreve configuração explícita do usuário.
     */
    private function registerLogChannel(): void
    {
        /** @var ConfigRepository $config */
        $config = $this->app->make('config');

        if (! $config->get('erede.logging.enabled', true)) {
            return;
        }

        $channel = $config->get('erede.logging.channel');

        if (! is_string($channel) || trim($channel) === '') {
            return;
        }

        if ($config->has("logging.channels.{$channel}")) {
            return;
        }

        $definition = $config->get('erede.logging.channel_config');

        if (! is_array($definition) || $definition === []) {
            return;
        }

        $config->set("logging.channels.{$channel}", $definition);
    }
}
