<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Link;
use eRede\Exceptions\ConfigurationException;
use eRede\Exceptions\eRedeException;
use eRede\Support\Config;
use eRede\Support\HttpOptions;
use eRede\Tests\Fixtures\BandeiraPura;
use eRede\Tests\Fixtures\MoedaBacked;
use eRede\Traits\ToArray;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

class FixtureComTipos
{
    use ToArray;

    public function __construct(
        public mixed $backed = null,
        public mixed $puro = null,
        public mixed $objetoSimples = null,
        public mixed $lista = null,
        public mixed $texto = null,
    ) {}
}

class ToArrayBranchesTest extends TestCase
{
    /**
     * `enum` é PHP 8.1 e o pacote suporta 8.0, então os enums vivem num arquivo
     * à parte carregado só aqui. Ver docs/php-8.0-compat.md.
     */
    private function exigeEnums(): void
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Enums exigem PHP 8.1+.');
        }

        require_once __DIR__.'/../Fixtures/Enums.php';
    }

    public function test_enum_backed_serializa_pelo_value(): void
    {
        $this->exigeEnums();

        $array = (new FixtureComTipos(backed: MoedaBacked::BRL))->toArray();

        $this->assertSame(['backed' => 'BRL'], $array);
    }

    public function test_enum_puro_serializa_pelo_name(): void
    {
        $this->exigeEnums();

        $array = (new FixtureComTipos(puro: BandeiraPura::VISA))->toArray();

        $this->assertSame(['puro' => 'VISA'], $array);
    }

    public function test_objeto_sem_to_array_e_convertido_por_suas_propriedades(): void
    {
        $obj = new stdClass;
        $obj->a = 1;
        $obj->b = 'dois';

        $array = (new FixtureComTipos(objetoSimples: $obj))->toArray();

        $this->assertSame(['objetoSimples' => ['a' => 1, 'b' => 'dois']], $array);
    }

    public function test_array_de_objetos_com_to_array_desce_recursivamente(): void
    {
        $array = (new FixtureComTipos(lista: [
            new Link(method: 'GET', rel: 'self', href: 'https://api/x'),
        ]))->toArray();

        $this->assertSame([
            'lista' => [
                ['method' => 'GET', 'rel' => 'self', 'href' => 'https://api/x'],
            ],
        ], $array);
    }

    public function test_ignore_nullable_falso_preserva_nulos(): void
    {
        $array = (new FixtureComTipos(texto: 'x'))->toArray(ignoreNullable: false);

        $this->assertArrayHasKey('backed', $array);
        $this->assertNull($array['backed']);
        $this->assertSame('x', $array['texto']);
    }

    public function test_chave_ja_minuscula_passa_intacta_no_snake_case(): void
    {
        $array = (new FixtureComTipos(texto: 'x'))->toArray(toSnakeCase: true);

        $this->assertSame(['texto' => 'x'], $array);
    }

    public function test_excecao_sem_return_code_devolve_nulo(): void
    {
        $e = new eRedeException('falhou', 500);

        $this->assertNull($e->returnCode());
        $this->assertSame([], $e->context());
    }

    public function test_excecao_expoe_return_code_e_causa_original(): void
    {
        $anterior = new RuntimeException('rede caiu');
        $e = new eRedeException('falhou', 400, $anterior, ['return_code' => 51]);

        $this->assertSame('51', $e->returnCode());
        $this->assertSame($anterior, $e->getPrevious());
        $this->assertSame(['return_code' => 51], $e->context());
    }

    public function test_configuration_exception_e_uma_erede_exception(): void
    {
        $this->assertInstanceOf(eRedeException::class, ConfigurationException::missingCredentials());
        $this->assertStringContainsString('EREDE_PV', ConfigurationException::missingCredentials()->getMessage());
        $this->assertStringContainsString('staging', ConfigurationException::invalidEnvironment('staging')->getMessage());
    }

    public function test_bypass_de_proxy_aceita_array_alem_de_string(): void
    {
        $options = HttpOptions::fromConfig(new Config(pv: 'pv', token: 'token', http: [
            'proxy' => ['http' => 'http://p:8080', 'no' => ['localhost', ' ', '127.0.0.1']],
        ]));

        $this->assertSame(['localhost', '127.0.0.1'], $options['proxy']['no']);
    }

    public function test_bypass_de_proxy_com_tipo_inesperado_e_ignorado(): void
    {
        $options = HttpOptions::fromConfig(new Config(pv: 'pv', token: 'token', http: [
            'proxy' => ['http' => 'http://p:8080', 'no' => 123],
        ]));

        $this->assertArrayNotHasKey('no', $options['proxy']);
    }
}
