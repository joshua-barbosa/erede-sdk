<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property string|null $refundId
 * @property string|null $refundDateTime
 * @property string|null $cancelId
 * @property string|null $status
 * @property int|null $amount
 */
class Refund
{
    use Attribute, ToArray;

    private ?string $refundId = null;

    private ?string $refundDateTime = null;

    private ?string $cancelId = null;

    private ?string $status = null;

    private ?int $amount = null;

    public function __construct(
        ?string $refundId = null,
        ?string $refundDateTime = null,
        ?string $cancelId = null,
        ?string $status = null,
        ?int $amount = null,
        ?array $fromData = null
    ) {
        $this->refundId = $refundId;
        $this->refundDateTime = $refundDateTime;
        $this->cancelId = $cancelId;
        $this->status = $status;
        $this->amount = $amount;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                $this->set(key: $key, value: $value);
            }
        }
    }

    public function getRefundId(): ?string
    {
        return $this->refundId;
    }

    public function setRefundId(?string $refundId): self
    {
        $this->refundId = $refundId;

        return $this;
    }

    public function getRefundDateTime(): ?string
    {
        return $this->refundDateTime;
    }

    public function setRefundDateTime(?string $refundDateTime): self
    {
        $this->refundDateTime = $refundDateTime;

        return $this;
    }

    public function getCancelId(): ?string
    {
        return $this->cancelId;
    }

    public function setCancelId(?string $cancelId): self
    {
        $this->cancelId = $cancelId;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }
}
