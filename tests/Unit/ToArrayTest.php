<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Amount;
use eRede\Classes\Transaction;
use eRede\Classes\Url;
use PHPUnit\Framework\TestCase;

class ToArrayTest extends TestCase
{
    public function test_converte_valores_monetarios_para_centavos(): void
    {
        $this->assertSame(1050, (new Amount(10.50))->getConvertedAmount());
        $this->assertSame(0, (new Amount(0.0))->getConvertedAmount());
        $this->assertSame(100, (new Amount(1.0))->getConvertedAmount());
    }

    public function test_to_array_omite_campos_nulos_por_padrao(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))->toArray();

        $this->assertSame(['reference' => 'pedido-1', 'amount' => 1050], $array);
        $this->assertArrayNotHasKey('cardNumber', $array);
    }

    public function test_to_array_serializa_o_cartao_com_as_chaves_da_api(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
            ->toArray();

        $this->assertSame('5448280000000007', $array['cardNumber']);
        $this->assertSame('123', $array['securityCode']);
        $this->assertSame(12, $array['expirationMonth']);
        $this->assertSame(2030, $array['expirationYear']);
        $this->assertSame(Transaction::CREDIT, $array['kind']);
    }

    public function test_to_array_desce_em_objetos_aninhados(): void
    {
        $array = (new Url('https://loja.com.br/callback'))->toArray();

        $this->assertSame([
            'url' => 'https://loja.com.br/callback',
            'kind' => Url::CALLBACK,
        ], $array);
    }

    public function test_o_modo_snake_case_converte_as_chaves(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
            ->toArray(ignoreNullable: true, toSnakeCase: true);

        $this->assertArrayHasKey('card_number', $array);
        $this->assertArrayHasKey('expiration_month', $array);
        $this->assertArrayNotHasKey('cardNumber', $array);
    }

    public function test_capture_false_e_enviado_para_autorizar_sem_capturar(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
            ->capture(false)
            ->toArray();

        $this->assertArrayHasKey('capture', $array);
        $this->assertFalse($array['capture']);
    }

    public function test_false_e_zero_sao_valores_validos_e_permanecem_no_payload(): void
    {
        $array = (new Transaction(amount: 0, reference: 'zero-dollar'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
            ->setSubscription(false)
            ->toArray();

        $this->assertSame(0, $array['amount']);
        $this->assertFalse($array['subscription']);
    }

    public function test_nulo_string_vazia_e_array_vazio_continuam_fora_do_payload(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->setSoftDescriptor('')
            ->toArray();

        $this->assertSame(['reference' => 'pedido-1', 'amount' => 1050], $array);
    }

    public function test_debito_e_sempre_capturado(): void
    {
        $array = (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->debitCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
            ->toArray();

        $this->assertTrue($array['capture']);
        $this->assertSame(Transaction::DEBIT, $array['kind']);
    }
}
