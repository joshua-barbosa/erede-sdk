<?php

namespace eRede\Tests\Unit;

use eRede\Support\Redactor;
use PHPUnit\Framework\TestCase;

class RedactorTest extends TestCase
{
    public function test_apaga_credenciais_no_primeiro_nivel(): void
    {
        $clean = Redactor::scrub(['access_token' => 'abc', 'status' => 200]);

        $this->assertSame(Redactor::MASK, $clean['access_token']);
        $this->assertSame(200, $clean['status']);
    }

    public function test_desce_em_arrays_aninhados(): void
    {
        $clean = Redactor::scrub(['return' => ['auth' => ['securityCode' => '123']]]);

        $this->assertSame(Redactor::MASK, $clean['return']['auth']['securityCode']);
    }

    public function test_mascara_o_pan_preservando_os_quatro_ultimos_digitos(): void
    {
        $clean = Redactor::scrub(['cardNumber' => '5448280000000007']);

        $this->assertSame('************0007', $clean['cardNumber']);
    }

    public function test_pan_curto_demais_e_apagado_por_completo(): void
    {
        $this->assertSame(Redactor::MASK, Redactor::scrub(['cardNumber' => '12'])['cardNumber']);
    }

    public function test_o_reconhecimento_de_chave_ignora_caixa_e_separadores(): void
    {
        $clean = Redactor::scrub(['Access-Token' => 'abc', 'CVV' => '999']);

        $this->assertSame(Redactor::MASK, $clean['Access-Token']);
        $this->assertSame(Redactor::MASK, $clean['CVV']);
    }

    public function test_dados_nao_sensiveis_passam_intactos(): void
    {
        $context = ['tid' => '123', 'amount' => 1050, 'last4' => '0007', 'links' => [['rel' => 'self']]];

        $this->assertSame($context, Redactor::scrub($context));
    }
}
