<?php

namespace eRede\Tests\Unit;

use eRede\Exceptions\ConfigurationException;
use eRede\Support\Config;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    #[Test]
    public function from_array_le_o_formato_publicado_do_config(): void
    {
        $config = Config::fromArray([
            'pv' => 'meu-pv',
            'token' => 'meu-token',
            'mode' => 'production',
            'http' => ['timeout' => 15],
        ]);

        $this->assertSame('meu-pv', $config->pv);
        $this->assertSame('production', $config->env);
        $this->assertTrue($config->isProduction());
        $this->assertSame(15, $config->timeout());
        // Chaves não informadas mantêm o padrão.
        $this->assertSame(10, $config->connectTimeout());
    }

    #[Test]
    public function strings_vazias_contam_como_ausencia_de_valor(): void
    {
        $config = Config::fromArray(['pv' => '   ', 'token' => '']);

        $this->assertNull($config->pv);
        $this->assertNull($config->token);
    }

    #[Test]
    public function with_nao_muta_a_instancia_original(): void
    {
        $original = new Config(pv: 'pv-1', token: 'token-1', env: 'sandbox');
        $novo = $original->with(pv: 'pv-2');

        $this->assertSame('pv-1', $original->pv);
        $this->assertSame('pv-2', $novo->pv);
        $this->assertNotSame($original, $novo);
    }

    #[Test]
    public function with_ignora_argumentos_nulos_e_preserva_o_resto(): void
    {
        $config = (new Config(pv: 'pv-1', token: 'token-1', env: 'production'))
            ->with(pv: null, token: null, env: null);

        $this->assertSame('pv-1', $config->pv);
        $this->assertSame('token-1', $config->token);
        $this->assertSame('production', $config->env);
    }

    #[Test]
    public function validate_exige_credenciais(): void
    {
        $this->expectException(ConfigurationException::class);

        (new Config(pv: 'pv', token: null))->validate();
    }

    #[Test]
    public function validate_rejeita_ambiente_desconhecido(): void
    {
        $this->expectException(ConfigurationException::class);

        (new Config(pv: 'pv', token: 'token', env: 'staging'))->validate();
    }

    #[Test]
    public function a_chave_de_cache_isola_ambiente_e_pv(): void
    {
        $sandbox = new Config(pv: 'pv-1', token: 't', env: 'sandbox');
        $producao = new Config(pv: 'pv-1', token: 't', env: 'production');
        $outroPv = new Config(pv: 'pv-2', token: 't', env: 'sandbox');

        $this->assertNotSame($sandbox->cacheKey(), $producao->cacheKey());
        $this->assertNotSame($sandbox->cacheKey(), $outroPv->cacheKey());
        // O PV nunca aparece em claro na chave.
        $this->assertStringNotContainsString('pv-1', $sandbox->cacheKey());
    }

    #[Test]
    public function timeouts_nunca_sao_zero_ou_negativos(): void
    {
        $config = new Config(pv: 'pv', token: 't', http: [
            'timeout' => 0,
            'connect_timeout' => -5,
            'auth_timeout' => 0,
        ]);

        $this->assertSame(1, $config->timeout());
        $this->assertSame(1, $config->connectTimeout());
        $this->assertSame(1, $config->authTimeout());
    }
}
