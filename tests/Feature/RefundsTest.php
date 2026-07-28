<?php

namespace eRede\Tests\Feature;

use eRede\Classes\Amount;
use eRede\Classes\Url;
use eRede\eRede;
use eRede\Exceptions\eRedeException;
use eRede\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class RefundsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    private function fakeAuth(array $additional = []): void
    {
        Http::fake(array_merge(
            ['*oauth2/token' => Http::response($this->oauthPayload())],
            $additional,
        ));
    }

    private function erede(): eRede
    {
        return $this->app->make(eRede::class);
    }

    #[Test]
    public function cria_um_estorno_com_valor_em_centavos_e_url_de_callback(): void
    {
        $this->fakeAuth(['*/refunds' => Http::response(['refundId' => 'r-1', 'returnCode' => '00'])]);

        $refund = $this->erede()->transactions('123')->refunds()->create(
            new Amount(10.50),
            new Url('https://loja.com.br/webhook'),
        );

        $this->assertSame('r-1', $refund->getRefundId());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/refunds')) {
                return false;
            }

            return $request->method() === 'POST'
                && $request['amount'] === 1050
                && $request['urls'][0]['url'] === 'https://loja.com.br/webhook'
                && $request['urls'][0]['kind'] === Url::CALLBACK;
        });
    }

    #[Test]
    public function create_aceita_tid_explicito_sobrescrevendo_o_do_componente(): void
    {
        $this->fakeAuth(['*/refunds' => Http::response(['returnCode' => '00'])]);

        $this->erede()->transactions('123')->refunds()->create(
            new Amount(10.50),
            new Url('https://loja.com.br/webhook'),
            tid: '999',
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/transactions/999/refunds'));
    }

    #[Test]
    public function consulta_um_estorno_por_id(): void
    {
        $this->fakeAuth(['*/refunds/r-1' => Http::response(['refundId' => 'r-1', 'status' => 'CONFIRMED'])]);

        $refund = $this->erede()->transactions('123')->refunds()->get('r-1');

        $this->assertSame('CONFIRMED', $refund->getStatus());
        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/transactions/123/refunds/r-1'));
    }

    #[Test]
    public function lista_estornos_por_tid_e_hidrata_o_refund_aninhado(): void
    {
        $this->fakeAuth([
            '*/refunds' => Http::response([
                'refunds' => ['refundId' => 'r-1', 'status' => 'CONFIRMED', 'amount' => 1050],
            ]),
        ]);

        $lista = $this->erede()->transactions('123')->refunds()->getByTid();

        $this->assertNotNull($lista->getRefunds());
        $this->assertSame('r-1', $lista->getRefunds()->getRefundId());
    }

    #[Test]
    public function set_tid_e_get_tid_do_componente(): void
    {
        $this->fakeAuth();

        $refunds = $this->erede()->transactions()->refunds();
        $this->assertNull($refunds->getTid());

        $refunds->setTid('123');
        $this->assertSame('123', $refunds->getTid());
    }

    #[Test]
    public function operacao_sem_tid_lanca_argumento_invalido(): void
    {
        $this->fakeAuth();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Transactions id not informed or invalid');

        $this->erede()->transactions()->refunds()->getByTid();
    }

    #[Test]
    public function get_sem_refund_id_lanca_argumento_invalido(): void
    {
        $this->fakeAuth();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Refund id not informed or invalid');

        $this->erede()->transactions('123')->refunds()->get(null);
    }

    #[Test]
    public function erro_no_estorno_vira_excecao_de_dominio(): void
    {
        $this->fakeAuth(['*/refunds' => Http::response(['returnCode' => '3'], 400)]);

        $this->expectException(eRedeException::class);
        $this->expectExceptionMessage('Parâmetro obrigatório não está presente');

        $this->erede()->transactions('123')->refunds()->create(
            new Amount(10.50),
            new Url('https://loja.com.br/webhook'),
        );
    }
}
