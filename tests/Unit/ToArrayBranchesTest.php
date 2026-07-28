<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Link;
use eRede\Exceptions\ConfigurationException;
use eRede\Exceptions\eRedeException;
use eRede\Support\Config;
use eRede\Support\HttpOptions;
use eRede\Traits\ToArray;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;

enum MoedaBacked: string
{
    case BRL = 'BRL';
}

enum BandeiraPura
{
    case VISA;
}

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
    #[Test]
    public function enum_backed_serializa_pelo_value(): void
    {
        $array = (new FixtureComTipos(backed: MoedaBacked::BRL))->toArray();

        $this->assertSame(['backed' => 'BRL'], $array);
    }

    #[Test]
    public function enum_puro_serializa_pelo_name(): void
    {
        $array = (new FixtureComTipos(puro: BandeiraPura::VISA))->toArray();

        $this->assertSame(['puro' => 'VISA'], $array);
    }

    #[Test]
    public function objeto_sem_to_array_e_convertido_por_suas_propriedades(): void
    {
        $obj = new stdClass;
        $obj->a = 1;
        $obj->b = 'dois';

        $array = (new FixtureComTipos(objetoSimples: $obj))->toArray();

        $this->assertSame(['objetoSimples' => ['a' => 1, 'b' => 'dois']], $array);
    }

    #[Test]
    public function array_de_objetos_com_to_array_desce_recursivamente(): void
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

    #[Test]
    public function ignore_nullable_falso_preserva_nulos(): void
    {
        $array = (new FixtureComTipos(texto: 'x'))->toArray(ignoreNullable: false);

        $this->assertArrayHasKey('backed', $array);
        $this->assertNull($array['backed']);
        $this->assertSame('x', $array['texto']);
    }

    #[Test]
    public function chave_ja_minuscula_passa_intacta_no_snake_case(): void
    {
        $array = (new FixtureComTipos(texto: 'x'))->toArray(toSnakeCase: true);

        $this->assertSame(['texto' => 'x'], $array);
    }

    #[Test]
    public function excecao_sem_return_code_devolve_nulo(): void
    {
        $e = new eRedeException('falhou', 500);

        $this->assertNull($e->returnCode());
        $this->assertSame([], $e->context());
    }

    #[Test]
    public function excecao_expoe_return_code_e_causa_original(): void
    {
        $anterior = new RuntimeException('rede caiu');
        $e = new eRedeException('falhou', 400, $anterior, ['return_code' => 51]);

        $this->assertSame('51', $e->returnCode());
        $this->assertSame($anterior, $e->getPrevious());
        $this->assertSame(['return_code' => 51], $e->context());
    }

    #[Test]
    public function configuration_exception_e_uma_erede_exception(): void
    {
        $this->assertInstanceOf(eRedeException::class, ConfigurationException::missingCredentials());
        $this->assertStringContainsString('EREDE_PV', ConfigurationException::missingCredentials()->getMessage());
        $this->assertStringContainsString('staging', ConfigurationException::invalidEnvironment('staging')->getMessage());
    }

    #[Test]
    public function bypass_de_proxy_aceita_array_alem_de_string(): void
    {
        $options = HttpOptions::fromConfig(new Config(pv: 'pv', token: 'token', http: [
            'proxy' => ['http' => 'http://p:8080', 'no' => ['localhost', ' ', '127.0.0.1']],
        ]));

        $this->assertSame(['localhost', '127.0.0.1'], $options['proxy']['no']);
    }

    #[Test]
    public function bypass_de_proxy_com_tipo_inesperado_e_ignorado(): void
    {
        $options = HttpOptions::fromConfig(new Config(pv: 'pv', token: 'token', http: [
            'proxy' => ['http' => 'http://p:8080', 'no' => 123],
        ]));

        $this->assertArrayNotHasKey('no', $options['proxy']);
    }
}
