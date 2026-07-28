<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Status;
use eRede\Classes\Transaction;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Trait Attribute — acesso genérico usado pela hidratação via `fromData`.
 */
class AttributeTest extends TestCase
{
    #[Test]
    public function set_e_get_leem_e_escrevem_propriedades_privadas(): void
    {
        $status = new Status;
        $status->set('status', 'CONFIRMED');

        $this->assertSame('CONFIRMED', $status->get('status'));
    }

    #[Test]
    public function get_devolve_nulo_para_propriedade_inexistente(): void
    {
        $this->assertNull((new Status)->get('nao_existe'));
    }

    #[Test]
    public function set_many_ignora_chaves_numericas(): void
    {
        $status = new Status;
        $status->setMany(status: 'PENDING', dateTime: '2026-07-28T10:00:00');

        $this->assertSame('PENDING', $status->get('status'));
        $this->assertSame('2026-07-28T10:00:00', $status->get('dateTime'));
    }

    #[Test]
    public function set_many_sem_argumentos_nao_faz_nada(): void
    {
        $status = new Status;
        $status->setMany();

        $this->assertNull($status->get('status'));
    }

    #[Test]
    public function get_many_devolve_apenas_as_chaves_existentes(): void
    {
        $transaction = new Transaction(amount: 10.5, reference: 'pedido-1');

        $valores = $transaction->getMany('reference', 'amount', 'nao_existe');

        $this->assertSame(['reference' => 'pedido-1', 'amount' => 1050], $valores);
        $this->assertArrayNotHasKey('nao_existe', $valores);
    }

    #[Test]
    public function get_many_sem_argumentos_devolve_array_vazio(): void
    {
        $this->assertSame([], (new Transaction)->getMany());
    }
}
