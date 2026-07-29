<?php

namespace eRede\Tests\Feature;

use eRede\eRede;
use eRede\Providers\eRedeServiceProvider;
use eRede\Support\Config;
use eRede\Support\HttpOptions;
use eRede\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class ConfigurationTest extends TestCase
{
    public function test_o_canal_de_log_erede_e_registrado_automaticamente(): void
    {
        $channel = $this->app->make('config')->get('logging.channels.erede');

        $this->assertIsArray($channel);
        $this->assertSame('daily', $channel['driver']);
    }

    public function test_o_canal_definido_pela_aplicacao_tem_precedencia(): void
    {
        $this->app->make('config')->set('logging.channels.erede', ['driver' => 'single', 'path' => '/tmp/meu.log']);

        // Re-executa o registro do provider sobre a config já existente.
        (new eRedeServiceProvider($this->app))->register();

        $this->assertSame('single', $this->app->make('config')->get('logging.channels.erede.driver'));
    }

    public function test_o_proxy_configurado_chega_nas_opcoes_da_requisicao(): void
    {
        $this->app->make('config')->set('erede.http.proxy', [
            'http' => 'http://proxy.local:8080',
            'https' => 'http://proxy.local:8080',
            'no' => 'localhost,127.0.0.1',
        ]);

        $options = HttpOptions::fromConfig(
            Config::fromArray($this->app->make('config')->get('erede'))
        );

        $this->assertSame('http://proxy.local:8080', $options['proxy']['http']);
        $this->assertSame(['localhost', '127.0.0.1'], $options['proxy']['no']);

        // E a instância sobe sem erro com o proxy aplicado.
        Http::fake(['*' => Http::response($this->oauthPayload())]);

        (new eRede(container: $this->app))->transactions();

        Http::assertSentCount(1);
    }

    public function test_o_singleton_do_container_devolve_sempre_a_mesma_instancia(): void
    {
        $this->assertSame($this->app->make(eRede::class), $this->app->make('erede'));
    }

    public function test_argumentos_explicitos_sobrescrevem_apenas_o_que_foi_informado(): void
    {
        $this->app->make('config')->set('erede.http.timeout', 42);

        $erede = new eRede(pv: 'outro-pv', container: $this->app);

        $this->assertSame('outro-pv', $erede->config()->pv);
        $this->assertSame('token-de-teste', $erede->config()->token);
        $this->assertSame(42, $erede->config()->timeout());
    }
}
