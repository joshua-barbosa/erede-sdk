<?php

namespace eRede\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use eRede\Classes\QrCode;
use eRede\Classes\QrCodeResponse;
use eRede\Classes\Transaction;
use eRede\Responses\RefundGet;
use eRede\Responses\Transaction as ResponsesTransaction;
use eRede\Responses\TransactionGet;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PixTest extends TestCase
{
    public function test_pix_define_kind_e_qr_code_sem_dados_de_cartao(): void
    {
        $transaction = (new Transaction(amount: 20.00, reference: 'pix-1'))
            ->pix('2030-01-15T23:59:00');

        $this->assertSame(Transaction::PIX, $transaction->getKind());
        $this->assertTrue($transaction->isPix());
        $this->assertFalse($transaction->isCredit());
        $this->assertSame([
            'kind' => 'Pix',
            'reference' => 'pix-1',
            'amount' => 2000,
            'qrCode' => ['dateTimeExpiration' => '2030-01-15T23:59:00'],
        ], $transaction->toArray());
    }

    public function test_order_id_entra_no_payload(): void
    {
        $transaction = (new Transaction(amount: 20.00, reference: 'pix-1'))
            ->pix('2030-01-15T23:59:00')
            ->setOrderId('ordem-1');

        $this->assertSame('ordem-1', $transaction->getOrderId());
        $this->assertSame('ordem-1', $transaction->toArray()['orderId']);
    }

    public function test_pix_nao_aceita_captura_posterior(): void
    {
        $transaction = (new Transaction(amount: 20.00, reference: 'pix-1'))->pix('2030-01-15T23:59:00');

        $this->expectException(InvalidArgumentException::class);

        $transaction->capture(false);
    }

    public function test_pix_recusa_transacao_que_ja_tem_dados_de_cartao(): void
    {
        $transaction = (new Transaction(amount: 20.00, reference: 'pix-1'))
            ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Pix transactions do not accept card data');

        $transaction->pix('2030-01-15T23:59:00');
    }

    public function test_pix_nunca_envia_o_campo_capture(): void
    {
        $antes = (new Transaction(amount: 20.00, reference: 'pix-1'))->capture()->pix('2030-01-15T23:59:00');
        $depois = (new Transaction(amount: 20.00, reference: 'pix-1'))->pix('2030-01-15T23:59:00')->capture();

        $this->assertArrayNotHasKey('capture', $antes->toArray());
        $this->assertArrayNotHasKey('capture', $depois->toArray());
    }

    public function test_refund_get_to_array_nao_expoe_a_lista_interna(): void
    {
        $dto = new RefundGet(fromData: ['refunds' => [['refundId' => 'r-1', 'status' => 'Done']]]);

        $this->assertSame(['refunds' => ['refundId' => 'r-1', 'status' => 'Done']], $dto->toArray());
        $this->assertArrayNotHasKey('refunds_list', $dto->toArray(toSnakeCase: true));
    }

    public function test_transaction_get_aceita_refunds_como_objeto_unico(): void
    {
        $dto = new TransactionGet(fromData: ['refunds' => ['refundId' => 'r-1', 'amount' => 1000]]);

        $this->assertCount(1, $dto->getRefunds());
        $this->assertSame('r-1', $dto->getRefunds()[0]->getRefundId());
    }

    public function test_qr_code_aceita_string_e_datetime(): void
    {
        $this->assertSame('2030-01-15T23:59:00', (new QrCode('2030-01-15T23:59:00'))->getDateTimeExpiration());

        $utc = new DateTimeImmutable('2030-06-01T12:00:00', new DateTimeZone('UTC'));
        $this->assertSame('2030-06-01T09:00:00', (new QrCode($utc))->getDateTimeExpiration());

        $qrCode = (new QrCode('2030-01-15T23:59:00'))->setDateTimeExpiration('2030-02-01T10:00:00');
        $this->assertSame(['dateTimeExpiration' => '2030-02-01T10:00:00'], $qrCode->toArray());
    }

    public function test_qr_code_recusa_expiracao_vazia(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QrCode('  ');
    }

    public function test_qr_code_response_hidrata_e_expoe_acessores(): void
    {
        $qr = new QrCodeResponse(fromData: [
            'dateTime' => '2030-01-01T08:27:21.000-03:00',
            'dateTimeExpiration' => '2030-01-15T23:59:00.000-03:00',
            'expirationQrCode' => '2030-01-15T23:59:00.000-03:00',
            'returnCode' => '00',
            'returnMessage' => 'Success.',
            'affiliation' => 12341088,
            'kind' => 'Pix',
            'reference' => 'pix-1',
            'amount' => 3900,
            'tid' => '40402307310827210012',
            'status' => 'Pending',
            'qrCodeImage' => 'aW1hZ2Vt',
            'qrCodeData' => '000201',
        ]);

        $this->assertSame('2030-01-01T08:27:21.000-03:00', $qr->getDateTime());
        $this->assertSame('2030-01-15T23:59:00.000-03:00', $qr->getDateTimeExpiration());
        $this->assertSame('2030-01-15T23:59:00.000-03:00', $qr->getExpirationQrCode());
        $this->assertSame('00', $qr->getReturnCode());
        $this->assertSame('Success.', $qr->getReturnMessage());
        $this->assertSame(12341088, $qr->getAffiliation());
        $this->assertSame('Pix', $qr->getKind());
        $this->assertSame('pix-1', $qr->getReference());
        $this->assertSame(3900, $qr->getAmount());
        $this->assertSame('40402307310827210012', $qr->getTid());
        $this->assertSame('Pending', $qr->getStatus());
        $this->assertSame('aW1hZ2Vt', $qr->getQrCodeImage());
        $this->assertSame('000201', $qr->getQrCodeData());
        $this->assertTrue($qr->isPending());
        $this->assertFalse($qr->isApproved());
        $this->assertFalse($qr->isCanceled());
    }

    public function test_response_transaction_sem_qr_code_response_mantem_null(): void
    {
        $dto = new ResponsesTransaction(fromData: ['tid' => '1', 'orderId' => 'ordem-1']);

        $this->assertNull($dto->getQrCodeResponse());
        $this->assertSame('ordem-1', $dto->getOrderId());

        $dto->setQrCodeResponse(new QrCodeResponse(qrCodeData: '000201'))->setOrderId('ordem-2');
        $this->assertSame('000201', $dto->getQrCodeResponse()->getQrCodeData());
        $this->assertSame('ordem-2', $dto->getOrderId());
    }

    public function test_transaction_get_setters_de_pix(): void
    {
        $dto = new TransactionGet;

        $this->assertNull($dto->getRefunds());
        $this->assertNull($dto->getRequestDateTime());

        $dto->setQrCodeResponse(new QrCodeResponse(status: 'Approved'));
        $dto->setRequestDateTime('2030-01-01T08:30:00.000-03:00');
        $dto->setRefunds([]);

        $this->assertTrue($dto->getQrCodeResponse()->isApproved());
        $this->assertSame('2030-01-01T08:30:00.000-03:00', $dto->getRequestDateTime());
        $this->assertSame([], $dto->getRefunds());
    }

    public function test_refund_get_continua_aceitando_refunds_como_objeto_unico(): void
    {
        $dto = new RefundGet(fromData: ['refunds' => ['refundId' => 'r-1', 'amount' => 1050]]);

        $this->assertSame('r-1', $dto->getRefunds()->getRefundId());
        $this->assertCount(1, $dto->getRefundsList());
    }

    public function test_refund_get_com_lista_vazia(): void
    {
        $dto = new RefundGet(fromData: ['refunds' => []]);

        $this->assertNotNull($dto->getRefunds());
        $this->assertSame([], $dto->getRefundsList());
    }
}
