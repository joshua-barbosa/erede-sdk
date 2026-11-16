<?php

namespace eRede\Tests\Feature;

use DateTimeImmutable;
use DateTimeZone;
use eRede\Classes\Amount;
use eRede\Classes\QrCodeResponse;
use eRede\Classes\Transaction;
use eRede\eRede;
use eRede\Exceptions\eRedeException;
use eRede\Tests\TestCase;
use Illuminate\Support\Facades\Http;

class PixTest extends TestCase
{
    private const TID = '40402307310827210012';

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

    private function erede(): eRede
    {
        return $this->app->make(eRede::class);
    }

    private function pix(): Transaction
    {
        return (new Transaction(amount: 39.00, reference: 'pix-1'))->pix('2030-01-15T23:59:00');
    }

    public function test_solicita_qr_code_pix_no_endpoint_de_transacoes(): void
    {
        $this->fakeAuth([
            '*/v2/transactions' => Http::response([
                'reference' => 'pix-1',
                'tid' => self::TID,
                'dateTime' => '2030-01-01T08:27:21.000-03:00',
                'amount' => 3900,
                'qrCodeResponse' => [
                    'dateTimeExpiration' => '2030-01-15T23:59:00.000-03:00',
                    'qrCodeImage' => 'aW1hZ2Vt',
                    'qrCodeData' => '00020101021226930014br.gov.bcb.pix',
                ],
                'returnCode' => '00',
                'returnMessage' => 'Success.',
            ]),
        ]);

        $response = $this->erede()->transactions()->create($this->pix());

        $this->assertSame(self::TID, $response->getTid());
        $this->assertSame('00', $response->getReturnCode());
        $this->assertInstanceOf(QrCodeResponse::class, $response->getQrCodeResponse());
        $this->assertSame('aW1hZ2Vt', $response->getQrCodeResponse()->getQrCodeImage());
        $this->assertSame('00020101021226930014br.gov.bcb.pix', $response->getQrCodeResponse()->getQrCodeData());
        $this->assertSame('2030-01-15T23:59:00.000-03:00', $response->getQrCodeResponse()->getDateTimeExpiration());

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/v2/transactions')) {
                return false;
            }

            return $request->method() === 'POST'
                && $request->data() === [
                    'kind' => 'Pix',
                    'reference' => 'pix-1',
                    'amount' => 3900,
                    'qrCode' => ['dateTimeExpiration' => '2030-01-15T23:59:00'],
                ];
        });
    }

    public function test_expiracao_em_datetime_e_convertida_para_o_horario_de_brasilia(): void
    {
        $expiration = new DateTimeImmutable('2030-01-16T02:59:00', new DateTimeZone('UTC'));

        $transaction = (new Transaction(amount: 10, reference: 'pix-2'))->pix($expiration);

        $this->assertTrue($transaction->isPix());
        $this->assertSame('2030-01-15T23:59:00', $transaction->getQrCode()->getDateTimeExpiration());
    }

    public function test_consulta_pix_pendente_hidrata_o_qr_code_response(): void
    {
        $this->fakeAuth([
            '*/v2/transactions/'.self::TID => Http::response([
                'requestDateTime' => '2030-01-01T08:30:00.000-03:00',
                'qrCodeResponse' => [
                    'dateTime' => '2030-01-01T08:27:21.000-03:00',
                    'returnCode' => '00',
                    'returnMessage' => 'Success.',
                    'affiliation' => 12341088,
                    'kind' => 'Pix',
                    'reference' => 'pix-1',
                    'amount' => 3900,
                    'tid' => self::TID,
                    'status' => 'Pending',
                    'expirationQrCode' => '2030-01-15T23:59:00.000-03:00',
                    'qrCodeImage' => 'aW1hZ2Vt',
                    'qrCodeData' => '000201',
                ],
            ]),
        ]);

        $consulta = $this->erede()->transactions(self::TID)->get();

        $this->assertNull($consulta->getAuthorization());
        $this->assertSame('2030-01-01T08:30:00.000-03:00', $consulta->getRequestDateTime());
        $this->assertSame('Pending', $consulta->getQrCodeResponse()->getStatus());
        $this->assertSame('Pix', $consulta->getQrCodeResponse()->getKind());
        $this->assertSame(3900, $consulta->getQrCodeResponse()->getAmount());
        $this->assertSame('000201', $consulta->getQrCodeResponse()->getQrCodeData());
        $this->assertSame('2030-01-15T23:59:00.000-03:00', $consulta->getQrCodeResponse()->getExpirationQrCode());
    }

    public function test_consulta_pix_pago_hidrata_authorization_capture_e_devolucoes(): void
    {
        $this->fakeAuth([
            '*/v2/transactions/'.self::TID => Http::response([
                'requestDateTime' => '2030-01-01T09:00:00.000-03:00',
                'authorization' => [
                    'returnCode' => '00',
                    'status' => 'Approved',
                    'reference' => 'pix-1',
                    'orderId' => 'ordem-1',
                    'tid' => self::TID,
                    'kind' => 'Pix',
                    'amount' => 3900,
                    'txid' => 'RELG000021001332453520230905T200143',
                    'endToEndId' => 'E0000000020241219172445877200001',
                ],
                'capture' => ['dateTime' => '2030-01-01T08:40:00.000-03:00', 'amount' => 3900],
                'refunds' => [
                    ['refundId' => 'r-1', 'refundDateTime' => '2030-01-02T08:00:00.000-03:00', 'status' => 'Done', 'amount' => 1000],
                    ['refundId' => 'r-2', 'status' => 'Denied', 'amount' => 500],
                ],
            ]),
        ]);

        $consulta = $this->erede()->transactions(self::TID)->get();

        $this->assertNull($consulta->getQrCodeResponse());
        $this->assertSame('Approved', $consulta->getAuthorization()->getStatus());
        $this->assertSame('ordem-1', $consulta->getAuthorization()->getOrderId());
        $this->assertSame('RELG000021001332453520230905T200143', $consulta->getAuthorization()->getTxid());
        $this->assertSame('E0000000020241219172445877200001', $consulta->getAuthorization()->getEndToEndId());
        $this->assertSame(3900, $consulta->getCapture()->getAmount());
        $this->assertCount(2, $consulta->getRefunds());
        $this->assertSame('r-1', $consulta->getRefunds()[0]->getRefundId());
        $this->assertSame('Denied', $consulta->getRefunds()[1]->getStatus());
    }

    public function test_devolucao_pix_envia_apenas_o_valor(): void
    {
        $this->fakeAuth([
            '*/refunds' => Http::response([
                'refundDateTime' => '2030-01-02T08:45:00.000-03:00',
                'returnCode' => '360',
                'returnMessage' => 'Refund request has been successful',
            ]),
        ]);

        $refund = $this->erede()->transactions(self::TID)->refunds()->create(new Amount(10.00));

        $this->assertSame('360', $refund->getReturnCode());
        $this->assertSame('2030-01-02T08:45:00.000-03:00', $refund->getRefundDateTime());

        Http::assertSent(function ($request) {
            if (! str_ends_with($request->url(), '/v2/transactions/'.self::TID.'/refunds')) {
                return false;
            }

            return $request->method() === 'POST' && $request->data() === ['amount' => 1000];
        });
    }

    public function test_consulta_de_devolucoes_por_tid_expoe_todas_as_devolucoes(): void
    {
        $this->fakeAuth([
            '*/refunds' => Http::response([
                'refunds' => [
                    ['refundId' => 'r-1', 'status' => 'Done', 'amount' => 1000],
                    ['refundId' => 'r-2', 'status' => 'Done', 'amount' => 500],
                ],
            ]),
        ]);

        $lista = $this->erede()->transactions(self::TID)->refunds()->getByTid();

        $this->assertSame('r-1', $lista->getRefunds()->getRefundId());
        $this->assertCount(2, $lista->getRefundsList());
        $this->assertSame('r-2', $lista->getRefundsList()[1]->getRefundId());
        $this->assertSame(500, $lista->getRefundsList()[1]->getAmount());
    }

    public function test_consulta_de_devolucao_pix_por_refund_id_traz_o_txid(): void
    {
        $this->fakeAuth([
            '*/refunds/r-1' => Http::response([
                'refundId' => 'r-1',
                'tid' => self::TID,
                'status' => 'Done',
                'amount' => 1000,
                'txid' => 'RELG000021001332453520230905T200143',
            ]),
        ]);

        $refund = $this->erede()->transactions(self::TID)->refunds()->get('r-1');

        $this->assertSame('RELG000021001332453520230905T200143', $refund->getTxid());
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/transactions/'.self::TID.'/refunds/r-1'));
    }

    public function test_qr_code_expirado_na_consulta_vira_excecao_de_dominio(): void
    {
        $this->fakeAuth([
            '*/v2/transactions/'.self::TID => Http::response([
                'qrCodeResponse' => ['returnCode' => '3036', 'returnMessage' => 'QrCode Expired.'],
            ], 422),
        ]);

        try {
            $this->erede()->transactions(self::TID)->get();
            $this->fail('Era esperada uma eRedeException.');
        } catch (eRedeException $e) {
            $this->assertSame('3036', $e->returnCode());
            $this->assertSame('QrCode expirado.', $e->getMessage());
        }
    }

    public function test_erro_na_solicitacao_do_qr_code_vira_excecao_de_dominio(): void
    {
        $this->fakeAuth(['*/v2/transactions' => Http::response(['returnCode' => '3090'], 422)]);

        $this->expectException(eRedeException::class);
        $this->expectExceptionMessage('Chave Pix Inválida na geração de qrCode.');

        $this->erede()->transactions()->create($this->pix());
    }
}
