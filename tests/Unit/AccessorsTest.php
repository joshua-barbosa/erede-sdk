<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Amount;
use eRede\Classes\Authorization;
use eRede\Classes\Capture;
use eRede\Classes\Link;
use eRede\Classes\Refund as ClassesRefund;
use eRede\Classes\Status;
use eRede\Classes\Transaction;
use eRede\Classes\Url;
use eRede\Responses\Refund as ResponsesRefund;
use eRede\Responses\RefundGet;
use eRede\Responses\Transaction as ResponsesTransaction;
use eRede\Responses\TransactionGet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Percorre todos os acessores dos DTOs por reflexão.
 *
 * São ~240 métodos escritos à mão; o risco real aqui é erro de copiar-e-colar
 * (um setX que grava na propriedade errada). Este teste pega exatamente isso.
 */
class AccessorsTest extends TestCase
{
    /**
     * Métodos que legitimamente não fazem round-trip.
     */
    private const NAO_FAZ_ROUND_TRIP = [
        // Converte reais em centavos na escrita: set(10.5) => get() === 1050.
        Transaction::class.'::setAmount',
    ];

    /** Vindos do trait Attribute, não são acessores de propriedade. */
    private const METODOS_DO_TRAIT = ['set', 'setMany'];

    /**
     * @return array<string,array{class-string,array<int,mixed>}>
     */
    public static function dtos(): array
    {
        return [
            'Classes\Amount' => [Amount::class, [10.5]],
            'Classes\Authorization' => [Authorization::class, []],
            'Classes\Capture' => [Capture::class, []],
            'Classes\Link' => [Link::class, []],
            'Classes\Refund' => [ClassesRefund::class, []],
            'Classes\Status' => [Status::class, []],
            'Classes\Transaction' => [Transaction::class, [10.5, 'pedido-1']],
            'Classes\Url' => [Url::class, ['https://loja.com.br/callback']],
            'Responses\Refund' => [ResponsesRefund::class, []],
            'Responses\RefundGet' => [RefundGet::class, []],
            'Responses\Transaction' => [ResponsesTransaction::class, []],
            'Responses\TransactionGet' => [TransactionGet::class, []],
        ];
    }

    #[Test]
    #[DataProvider('dtos')]
    public function todo_setter_tem_getter_e_faz_round_trip(string $class, array $args): void
    {
        $dto = new $class(...$args);
        $reflection = new ReflectionClass($dto);
        $exercitados = 0;

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            $nome = $method->getName();

            if (! str_starts_with($nome, 'set') || in_array($nome, self::METODOS_DO_TRAIT, true)) {
                continue;
            }

            if ($method->getNumberOfParameters() !== 1 || $method->getParameters()[0]->isVariadic()) {
                continue;
            }

            if (in_array($class.'::'.$nome, self::NAO_FAZ_ROUND_TRIP, true)) {
                continue;
            }

            $propriedade = lcfirst(substr($nome, 3));
            $getter = $this->getterDe($reflection, $propriedade);

            $this->assertNotNull($getter, "{$class}::{$nome}() não tem getter correspondente.");

            $valor = $this->amostraPara($method->getParameters()[0]);
            $method->invoke($dto, $valor);

            $this->assertSame(
                $valor,
                $getter->invoke($dto),
                "{$class}::{$nome}() não faz round-trip com {$getter->getName()}() — provável escrita na propriedade errada.",
            );

            $exercitados++;
        }

        $this->assertGreaterThan(0, $exercitados, "Nenhum setter exercitado em {$class}.");
    }

    #[Test]
    public function set_amount_de_transaction_converte_para_centavos_na_escrita(): void
    {
        // Documenta a exclusão acima: a assimetria é intencional.
        $transaction = new Transaction;
        $transaction->setAmount(10.5);

        $this->assertSame(1050, $transaction->getAmount());
    }

    private function getterDe(ReflectionClass $reflection, string $propriedade): ?ReflectionMethod
    {
        foreach (['get'.ucfirst($propriedade), 'is'.ucfirst($propriedade)] as $candidato) {
            if ($reflection->hasMethod($candidato)) {
                return $reflection->getMethod($candidato);
            }
        }

        return null;
    }

    private function amostraPara(ReflectionParameter $parametro): mixed
    {
        $tipo = $parametro->getType();

        $nome = $tipo instanceof ReflectionNamedType ? $tipo->getName() : 'string';

        return match ($nome) {
            'string' => 'valor-'.$parametro->getName(),
            'int' => 42,
            'float' => 1.5,
            'bool' => true,
            'array' => ['chave' => 'valor'],
            default => new $nome,
        };
    }
}
