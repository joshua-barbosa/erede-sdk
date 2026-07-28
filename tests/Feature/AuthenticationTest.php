<?php

namespace eRede\Tests\Feature;

use eRede\eRede;
use eRede\Exceptions\ConfigurationException;
use eRede\Exceptions\eRedeException;
use eRede\Support\Redactor;
use eRede\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\AbstractLogger;

class AuthenticationTest extends TestCase
{
    #[Test]
    public function autentica_e_guarda_o_access_token_em_cache(): void
    {
        Http::fake([
            '*oauth2/token' => Http::response($this->oauthPayload(expiresIn: 3600)),
            '*' => Http::response(['returnCode' => '00']),
        ]);

        $erede = $this->app->make(eRede::class);
        $erede->transactions();

        $cached = Cache::get($erede->config()->cacheKey());

        $this->assertIsArray($cached);
        $this->assertSame('token-oauth-fake', $cached['access_token']);
        $this->assertSame('Bearer', $cached['token_type']);
    }

    #[Test]
    public function reaproveita_o_token_em_cache_sem_reautenticar(): void
    {
        Http::fake([
            '*oauth2/token' => Http::response($this->oauthPayload()),
            '*' => Http::response(['returnCode' => '00']),
        ]);

        $erede = $this->app->make(eRede::class);

        $erede->transactions();
        $erede->transactions();
        $erede->transactions();

        Http::assertSentCount(1);
    }

    #[Test]
    public function forget_access_token_forca_nova_autenticacao(): void
    {
        Http::fake([
            '*oauth2/token' => Http::response($this->oauthPayload()),
            '*' => Http::response(['returnCode' => '00']),
        ]);

        $erede = $this->app->make(eRede::class);

        $erede->transactions();
        $erede->forgetAccessToken();
        $erede->transactions();

        Http::assertSentCount(2);
    }

    #[Test]
    public function o_ttl_do_cache_desconta_a_margem_de_seguranca(): void
    {
        Http::fake(['*oauth2/token' => Http::response($this->oauthPayload(expiresIn: 300))]);

        $erede = $this->app->make(eRede::class);
        $erede->transactions();

        // 300 - 60 de skew: o token some do cache antes de expirar de fato.
        $this->travel(241)->seconds();

        $this->assertNull(Cache::get($erede->config()->cacheKey()));
    }

    #[Test]
    public function falha_de_autenticacao_lanca_excecao_de_dominio(): void
    {
        Http::fake(['*oauth2/token' => Http::response(['error' => 'invalid_client'], 401)]);

        $this->expectException(eRedeException::class);
        $this->expectExceptionMessage('Não foi possível conectar com o meio de pagamento.');

        $this->app->make(eRede::class)->transactions();
    }

    #[Test]
    public function resposta_de_autenticacao_sem_access_token_e_tratada_como_falha(): void
    {
        Http::fake(['*oauth2/token' => Http::response(['token_type' => 'Bearer'], 200)]);

        $this->expectException(eRedeException::class);

        $this->app->make(eRede::class)->transactions();
    }

    #[Test]
    public function o_log_de_falha_nao_vaza_dados_sensiveis(): void
    {
        Http::fake([
            '*oauth2/token' => Http::response([
                'error' => 'invalid_client',
                'access_token' => 'nao-deveria-aparecer',
            ], 401),
        ]);

        $spy = new class extends AbstractLogger
        {
            /** @var array<int,array{level:mixed,message:mixed,context:array}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = compact('level', 'message', 'context');
            }
        };

        // Sem channel(), o Logger do SDK usa a instância diretamente.
        $this->app->instance('log', $spy);

        try {
            $this->app->make(eRede::class)->transactions();
        } catch (eRedeException) {
            // esperado
        }

        $this->assertCount(1, $spy->records);
        $this->assertSame(Redactor::MASK, $spy->records[0]['context']['return']['access_token']);
        $this->assertSame('invalid_client', $spy->records[0]['context']['return']['error']);
    }

    #[Test]
    public function credenciais_ausentes_lancam_erro_de_configuracao(): void
    {
        $this->app->make('config')->set('erede.pv', null);
        $this->app->make('config')->set('erede.token', null);

        $this->expectException(ConfigurationException::class);

        new eRede(container: $this->app);
    }

    #[Test]
    public function ambiente_invalido_lanca_erro_de_configuracao(): void
    {
        $this->expectException(ConfigurationException::class);

        new eRede(pv: 'pv', token: 'token', env: 'homologacao', container: $this->app);
    }

    #[Test]
    public function producao_usa_as_urls_de_producao(): void
    {
        Http::fake(['*' => Http::response($this->oauthPayload())]);

        (new eRede(pv: 'pv', token: 'token', env: 'production', container: $this->app))->transactions();

        Http::assertSent(fn ($request) => $request->url() === eRede::URL['production-oauth']);
    }

    #[Test]
    public function ambientes_diferentes_nao_compartilham_o_token_em_cache(): void
    {
        $sandbox = new eRede(pv: 'pv', token: 'token', env: 'sandbox', container: $this->app);
        $producao = new eRede(pv: 'pv', token: 'token', env: 'production', container: $this->app);

        $this->assertNotSame($sandbox->config()->cacheKey(), $producao->config()->cacheKey());
    }
}
