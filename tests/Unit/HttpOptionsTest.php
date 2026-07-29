<?php

namespace eRede\Tests\Unit;

use eRede\Support\Config;
use eRede\Support\HttpOptions;
use PHPUnit\Framework\TestCase;

class HttpOptionsTest extends TestCase
{
    /** @param array<string,mixed> $http */
    private function options(array $http): array
    {
        return HttpOptions::fromConfig(new Config(pv: 'pv', token: 'token', http: $http));
    }

    public function test_proxy_como_string_e_repassado_direto(): void
    {
        $options = $this->options(['proxy' => 'http://proxy.local:8080']);

        $this->assertSame('http://proxy.local:8080', $options['proxy']);
    }

    public function test_proxy_por_protocolo_monta_o_array_do_guzzle(): void
    {
        $options = $this->options([
            'proxy' => ['http' => 'http://p1:8080', 'https' => 'http://p2:8443'],
        ]);

        $this->assertSame(['http' => 'http://p1:8080', 'https' => 'http://p2:8443'], $options['proxy']);
    }

    public function test_a_lista_de_bypass_aceita_string_separada_por_virgula(): void
    {
        $options = $this->options([
            'proxy' => ['http' => 'http://p:8080', 'no' => 'localhost, 127.0.0.1 ,.interno'],
        ]);

        $this->assertSame(['localhost', '127.0.0.1', '.interno'], $options['proxy']['no']);
    }

    public function test_proxy_nao_configurado_nao_gera_a_opcao(): void
    {
        $this->assertArrayNotHasKey('proxy', $this->options([]));
        $this->assertArrayNotHasKey('proxy', $this->options(['proxy' => null]));
        $this->assertArrayNotHasKey('proxy', $this->options(['proxy' => '   ']));
    }

    public function test_bypass_sem_proxy_definido_e_descartado(): void
    {
        // Passar apenas `no` ao Guzzle não teria efeito nenhum.
        $this->assertArrayNotHasKey('proxy', $this->options(['proxy' => ['no' => 'localhost']]));
    }

    public function test_env_vazio_nao_vira_proxy(): void
    {
        // env('EREDE_PROXY') ausente devolve null nas duas chaves.
        $options = $this->options(['proxy' => ['http' => null, 'https' => null, 'no' => null]]);

        $this->assertArrayNotHasKey('proxy', $options);
    }

    public function test_verify_normaliza_strings_vindas_do_env(): void
    {
        // Em laço, e não em data provider: atributos e anotações de provider
        // divergem entre PHPUnit 9 (Laravel 8) e 12, e o laço funciona em todos.
        $casos = [
            'bool false' => [false, false],
            'bool true' => [true, true],
            'string false' => ['false', false],
            'string true' => ['true', true],
            'zero' => ['0', false],
            'ca bundle' => ['/etc/ssl/certs/ca.pem', '/etc/ssl/certs/ca.pem'],
        ];

        foreach ($casos as $nome => [$input, $esperado]) {
            $this->assertSame(
                $esperado,
                $this->options(['verify' => $input])['verify'],
                "caso: {$nome}",
            );
        }
    }

    public function test_connect_timeout_sempre_acompanha_as_opcoes(): void
    {
        $this->assertSame(10, $this->options([])['connect_timeout']);
        $this->assertSame(5, $this->options(['connect_timeout' => 5])['connect_timeout']);
    }
}
