<?php

namespace eRede\Tests\Unit;

use eRede\Classes\Authorization;
use eRede\Classes\Capture;
use eRede\Classes\Link;
use eRede\Classes\Refund as ClassesRefund;
use eRede\Classes\ReturnResponse;
use eRede\Classes\Status;
use eRede\Responses\Refund as ResponsesRefund;
use eRede\Responses\RefundGet;
use eRede\Responses\Transaction as ResponsesTransaction;
use eRede\Responses\TransactionGet;
use PHPUnit\Framework\TestCase;

/**
 * Hidratação dos DTOs a partir do JSON da Rede (parâmetro `fromData`).
 */
class HydrationTest extends TestCase
{
    public function test_response_transaction_hidrata_campos_e_links(): void
    {
        $dto = new ResponsesTransaction(fromData: [
            'reference' => 'pedido-1',
            'tid' => '30161009000000000001',
            'nsu' => '000001',
            'authorizationCode' => 'ABC123',
            'amount' => 1050,
            'cardBin' => '544828',
            'last4' => '0007',
            'returnCode' => '00',
            'returnMessage' => 'Success.',
            'links' => [
                ['method' => 'GET', 'rel' => 'transaction', 'href' => 'https://api/v2/transactions/1'],
                ['method' => 'POST', 'rel' => 'refund', 'href' => 'https://api/v1/refunds'],
            ],
        ]);

        $this->assertSame('pedido-1', $dto->getReference());
        $this->assertSame('30161009000000000001', $dto->getTid());
        $this->assertSame('544828', $dto->getCardBin());
        $this->assertSame('0007', $dto->getLast4());
        $this->assertCount(2, $dto->getLinks());
        $this->assertInstanceOf(Link::class, $dto->getLinks()[0]);
        $this->assertSame('transaction', $dto->getLinks()[0]->getRel());
        $this->assertSame('https://api/v1/refunds', $dto->getLinks()[1]->getHref());
    }

    public function test_response_transaction_sem_links_mantem_a_lista_nula(): void
    {
        $dto = new ResponsesTransaction(fromData: ['tid' => '1']);

        $this->assertNull($dto->getLinks());
    }

    public function test_transaction_get_hidrata_authorization_e_capture(): void
    {
        $dto = new TransactionGet(fromData: [
            'authorization' => [
                'tid' => '123',
                'status' => 'Approved',
                'returnCode' => '00',
                'amount' => 1050,
                'installments' => 3,
                'cardHolderName' => 'JOAO DA SILVA',
                'subscription' => false,
            ],
            'capture' => [
                'dateTime' => '2026-07-28T10:00:00',
                'nsu' => '000002',
                'amount' => 1050,
            ],
            'links' => [['method' => 'GET', 'rel' => 'self', 'href' => 'https://api/x']],
        ]);

        $this->assertInstanceOf(Authorization::class, $dto->getAuthorization());
        $this->assertSame('Approved', $dto->getAuthorization()->getStatus());
        $this->assertSame(3, $dto->getAuthorization()->getInstallments());
        $this->assertFalse($dto->getAuthorization()->isSubscription());

        $this->assertInstanceOf(Capture::class, $dto->getCapture());
        $this->assertSame('000002', $dto->getCapture()->getNsu());
        $this->assertSame(1050, $dto->getCapture()->getAmount());

        $this->assertCount(1, $dto->getLinks());
    }

    public function test_transaction_get_com_capture_ausente(): void
    {
        $dto = new TransactionGet(fromData: ['authorization' => ['tid' => '123']]);

        $this->assertNotNull($dto->getAuthorization());
        $this->assertNull($dto->getCapture());
    }

    public function test_response_refund_hidrata_status_history_e_links(): void
    {
        $dto = new ResponsesRefund(fromData: [
            'returnCode' => '00',
            'returnMessage' => 'Success.',
            'refundId' => 'r-1',
            'tid' => '123',
            'amount' => 1050,
            'status' => 'PENDING',
            'statusHistory' => [
                ['status' => 'PENDING', 'dateTime' => '2026-07-28T10:00:00'],
                ['status' => 'CONFIRMED', 'dateTime' => '2026-07-28T11:00:00'],
            ],
            'links' => [['method' => 'GET', 'rel' => 'self', 'href' => 'https://api/x']],
        ]);

        $this->assertSame('r-1', $dto->getRefundId());
        $this->assertSame('PENDING', $dto->getStatus());
        $this->assertCount(2, $dto->getStatusHistory());
        $this->assertInstanceOf(Status::class, $dto->getStatusHistory()[0]);
        $this->assertSame('CONFIRMED', $dto->getStatusHistory()[1]->getStatus());
        $this->assertCount(1, $dto->getLinks());
    }

    public function test_refund_get_hidrata_o_refund_aninhado(): void
    {
        $dto = new RefundGet(fromData: [
            'refunds' => ['refundId' => 'r-1', 'status' => 'CONFIRMED', 'amount' => 1050],
            'links' => [['method' => 'GET', 'rel' => 'self', 'href' => 'https://api/x']],
        ]);

        $this->assertInstanceOf(ClassesRefund::class, $dto->getRefunds());
        $this->assertSame('r-1', $dto->getRefunds()->getRefundId());
        $this->assertSame('CONFIRMED', $dto->getRefunds()->getStatus());
        $this->assertCount(1, $dto->getLinks());
    }

    public function test_refund_get_com_refunds_nao_array_gera_refund_vazio(): void
    {
        $dto = new RefundGet(fromData: ['refunds' => 'inesperado']);

        $this->assertInstanceOf(ClassesRefund::class, $dto->getRefunds());
        $this->assertNull($dto->getRefunds()->getRefundId());
    }

    public function test_link_e_status_hidratam_via_from_data(): void
    {
        $link = new Link(fromData: ['method' => 'GET', 'rel' => 'self', 'href' => 'https://api/x']);
        $status = new Status(fromData: ['status' => 'CONFIRMED', 'dateTime' => '2026-07-28T11:00:00']);

        $this->assertSame('GET', $link->getMethod());
        $this->assertSame('https://api/x', $link->getHref());
        $this->assertSame('CONFIRMED', $status->getStatus());
        $this->assertSame('2026-07-28T11:00:00', $status->getDateTime());
    }

    public function test_argumentos_nomeados_do_construtor_tambem_populam(): void
    {
        $link = new Link(method: 'POST', rel: 'refund', href: 'https://api/y');

        $this->assertSame('POST', $link->getMethod());
        $this->assertSame('refund', $link->getRel());
    }

    public function test_return_response_traduz_codigos_conhecidos(): void
    {
        $this->assertTrue(ReturnResponse::existsReturnCode('00'));
        $this->assertSame('Sucesso', ReturnResponse::getReturnMessage('00'));
        $this->assertSame('Parâmetro obrigatório não está presente', ReturnResponse::getReturnMessage('3'));
    }

    public function test_return_response_lida_com_codigo_desconhecido_ou_nulo(): void
    {
        $this->assertFalse(ReturnResponse::existsReturnCode('codigo-inexistente'));
        $this->assertIsString(ReturnResponse::getReturnMessage('codigo-inexistente'));
        $this->assertIsString(ReturnResponse::getReturnMessage(null));
    }
}
