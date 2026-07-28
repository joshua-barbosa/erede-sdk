<?php

namespace eRede\Components;

use eRede\Classes\Amount;
use eRede\Classes\Transaction;
use eRede\Responses\Transaction as ResponsesTransaction;
use eRede\Responses\TransactionGet as ResponseTransactionGet;
use eRede\Traits\RetrieveResponse;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Transactions
{
    use RetrieveResponse;

    public const BASE_URL = '/v2/transactions';

    public function __construct(
        private PendingRequest $request,
        private ?string $tid = null,
        private ?LoggerInterface $logger = null,
    ) {}

    public function setTid(string $tid): self
    {
        $this->tid = $tid;

        return $this;
    }

    public function getTid(): ?string
    {
        return $this->tid;
    }

    private function url(string $path): string
    {
        return self::BASE_URL.$path;
    }

    /**
     * Resolve o tid do argumento ou do estado do componente.
     *
     * @throws InvalidArgumentException
     */
    private function resolveTid(?string $tid): string
    {
        $tid = $tid ?: $this->tid;

        if (! $tid) {
            throw new InvalidArgumentException('Transactions id not informed or invalid');
        }

        return $tid;
    }

    public function create(Transaction $transaction): ResponsesTransaction
    {
        $response = $this->request->post(
            $this->url(''),
            $transaction->toArray(ignoreNullable: true, toSnakeCase: false),
        );

        return new ResponsesTransaction(fromData: $this->retrieveResponse($response));
    }

    public function get(?string $tid = null): ResponseTransactionGet
    {
        $response = $this->request->get($this->url('/'.$this->resolveTid($tid)));

        return new ResponseTransactionGet(fromData: $this->retrieveResponse($response));
    }

    public function getByReference(string $reference): ResponseTransactionGet
    {
        $response = $this->request->get($this->url(''), ['reference' => $reference]);

        return new ResponseTransactionGet(fromData: $this->retrieveResponse($response));
    }

    public function capture(Amount $amount, ?string $tid = null): ResponsesTransaction
    {
        $response = $this->request->put(
            $this->url('/'.$this->resolveTid($tid)),
            ['amount' => $amount->getConvertedAmount()],
        );

        return new ResponsesTransaction(fromData: $this->retrieveResponse($response));
    }

    public function refunds(?string $tid = null): Refunds
    {
        return new Refunds($this->request, $tid ?: $this->tid, null, $this->logger);
    }
}
