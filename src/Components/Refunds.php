<?php

namespace eRede\Components;

use eRede\Classes\Amount;
use eRede\Classes\Url;
use eRede\Responses\Refund;
use eRede\Responses\RefundGet;
use eRede\Traits\RetrieveResponse;
use Illuminate\Http\Client\PendingRequest;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

class Refunds
{
    use RetrieveResponse;

    public const BASE_URL = '/v1/transactions/__tid__/refunds';

    public function __construct(
        private PendingRequest $request,
        private ?string $tid = null,
        private ?string $refundId = null,
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
        if (! $this->tid) {
            throw new InvalidArgumentException('Transactions id not informed or invalid');
        }

        return str_replace('__tid__', $this->tid, self::BASE_URL.$path);
    }

    public function create(Amount $amount, Url $callback, ?string $tid = null): Refund
    {
        if ($tid) {
            $this->tid = $tid;
        }

        $response = $this->request->post($this->url(''), [
            'amount' => $amount->getConvertedAmount(),
            'urls' => [$callback->toArray()],
        ]);

        return new Refund(fromData: $this->retrieveResponse($response));
    }

    public function get(?string $refundId = null, ?string $tid = null): Refund
    {
        $refundId = $refundId ?: $this->refundId;

        if (! $refundId) {
            throw new InvalidArgumentException('Refund id not informed or invalid');
        }

        if ($tid) {
            $this->tid = $tid;
        }

        $response = $this->request->get($this->url("/{$refundId}"));

        return new Refund(fromData: $this->retrieveResponse($response));
    }

    public function getByTid(?string $tid = null): RefundGet
    {
        if ($tid) {
            $this->tid = $tid;
        }

        $response = $this->request->get($this->url(''));

        return new RefundGet(fromData: $this->retrieveResponse($response));
    }
}
