<?php

namespace eRede\Tests\Feature;

use eRede\Classes\Amount;
use eRede\Classes\Transaction;
use eRede\eRede;
use eRede\Exceptions\eRedeException;
use eRede\Tests\TestCase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

class TransactionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Só existe a partir do Laravel 8.66; no 8.x antigo simplesmente não trava.
        if (method_exists(Http::getFacadeRoot(), 'preventStrayRequests')) {
            Http::preventStrayRequests();
        }
    }

    private function fakeAuth(array $additional = []): void
    {
        Http::fake(array_merge(
            ['*oauth2/token' => Http::response($this->oauthPayload())],
            $additional,
        ));
    }

    private function transaction(): Transaction
    {
        return (new Transaction(amount: 10.50, reference: 'pedido-1'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA');
    }

    public function test_cria_uma_transacao_de_credito(): void
    {
        $this->fakeAuth([
            '*/v2/transactions' => Http::response([
                'reference' => 'pedido-1',
                'tid' => '30161009000000000001',
                'nsu' => '000001',
                'authorizationCode' => 'ABC123',
                'amount' => 1050,
                'returnCode' => '00',
                'returnMessage' => 'Success.',
            ]),
        ]);

        $response = $this->app->make(eRede::class)->transactions()->create($this->transaction());

        $this->assertSame('30161009000000000001', $response->getTid());
        $this->assertSame('00', $response->getReturnCode());
        $this->assertSame(1050, $response->getAmount());
    }

    public function test_o_valor_enviado_vai_em_centavos(): void
    {
        $this->fakeAuth(['*/v2/transactions' => Http::response(['returnCode' => '00'])]);

        $this->app->make(eRede::class)->transactions()->create($this->transaction());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/transactions')) {
                return false;
            }

            return $request['amount'] === 1050 && $request['kind'] === Transaction::CREDIT;
        });
    }

    public function test_o_bearer_token_obtido_no_oauth_e_reenviado_na_transacao(): void
    {
        $this->fakeAuth(['*/v2/transactions' => Http::response(['returnCode' => '00'])]);

        $this->app->make(eRede::class)->transactions()->create($this->transaction());

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/transactions')) {
                return false;
            }

            return $request->hasHeader('Authorization', 'Bearer token-oauth-fake');
        });
    }

    public function test_erro_com_return_code_traduz_a_mensagem_da_rede(): void
    {
        $this->fakeAuth([
            '*/v2/transactions' => Http::response(['returnCode' => '3'], 400),
        ]);

        try {
            $this->app->make(eRede::class)->transactions()->create($this->transaction());
            $this->fail('Era esperada uma eRedeException.');
        } catch (eRedeException $e) {
            $this->assertSame('Parâmetro obrigatório não está presente', $e->getMessage());
            $this->assertSame('3', $e->returnCode());
            $this->assertSame(400, $e->getCode());
        }
    }

    public function test_erro_401_reporta_falha_de_conexao(): void
    {
        $this->fakeAuth(['*/v2/transactions' => Http::response(['returnCode' => '3'], 401)]);

        $this->expectException(eRedeException::class);
        $this->expectExceptionMessage('Não foi possível conectar com o meio de pagamento.');

        $this->app->make(eRede::class)->transactions()->create($this->transaction());
    }

    public function test_erro_sem_return_code_ainda_lanca_excecao(): void
    {
        $this->fakeAuth(['*/v2/transactions' => Http::response('<html>502</html>', 502)]);

        $this->expectException(eRedeException::class);

        $this->app->make(eRede::class)->transactions()->create($this->transaction());
    }

    public function test_consulta_por_tid_monta_a_url_e_hidrata_authorization(): void
    {
        $this->fakeAuth([
            '*/v2/transactions/123*' => Http::response([
                'authorization' => [
                    'tid' => '123',
                    'status' => 'Approved',
                    'returnCode' => '00',
                    'amount' => 1050,
                ],
            ]),
        ]);

        $response = $this->app->make(eRede::class)->transactions('123')->get();

        $this->assertNotNull($response->getAuthorization());
        $this->assertSame('Approved', $response->getAuthorization()->getStatus());
        $this->assertSame(1050, $response->getAuthorization()->getAmount());
    }

    public function test_return_code_dentro_de_authorization_tambem_e_traduzido(): void
    {
        $this->fakeAuth([
            '*/v2/transactions/123*' => Http::response([
                'authorization' => ['returnCode' => '3'],
            ], 400),
        ]);

        $this->expectException(eRedeException::class);
        $this->expectExceptionMessage('Parâmetro obrigatório não está presente');

        $this->app->make(eRede::class)->transactions('123')->get();
    }

    public function test_consulta_sem_tid_lanca_argumento_invalido(): void
    {
        $this->fakeAuth();

        $this->expectException(InvalidArgumentException::class);

        $this->app->make(eRede::class)->transactions()->get();
    }

    public function test_captura_envia_o_valor_convertido_via_put(): void
    {
        $this->fakeAuth(['*/v2/transactions/123' => Http::response(['returnCode' => '00'])]);

        $this->app->make(eRede::class)->transactions('123')->capture(new Amount(10.50));

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/transactions/123')) {
                return false;
            }

            return $request->method() === 'PUT' && $request['amount'] === 1050;
        });
    }

    public function test_refunds_herda_o_tid_da_transacao(): void
    {
        $this->fakeAuth(['*/refunds*' => Http::response(['returnCode' => '00'])]);

        $this->app->make(eRede::class)->transactions('123')->refunds()->getByTid();

        Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/transactions/123/refunds'));
    }
}
