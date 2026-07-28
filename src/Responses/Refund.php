<?php

namespace eRede\Responses;

use eRede\Classes\Link;
use eRede\Classes\Status;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property string|null $returnCode
 * @property string|null $returnMessage
 * @property string|null $refundId
 * @property string|null $tid
 * @property string|null $nsu
 * @property string|null $refundDateTime
 * @property string|null $cancelId
 * @property int|null $amount
 * @property string|null $status
 * @property array<int,Status>|null $statusHistory
 * @property array<int,Link>|null $links
 */
class Refund
{
    use Attribute, ToArray;

    private ?string $returnCode = null;

    private ?string $returnMessage = null;

    private ?string $refundId = null;

    private ?string $tid = null;

    private ?string $nsu = null;

    private ?string $refundDateTime = null;

    private ?string $cancelId = null;

    private ?int $amount = null;

    private ?string $status = null;

    private ?array $statusHistory = null;

    private ?array $links = null;

    public function __construct(
        ?string $returnCode = null,
        ?string $returnMessage = null,
        ?string $refundId = null,
        ?string $tid = null,
        ?string $nsu = null,
        ?string $refundDateTime = null,
        ?string $cancelId = null,
        ?int $amount = null,
        ?string $status = null,
        ?array $statusHistory = null,
        ?array $links = null,
        ?array $fromData = null
    ) {
        $this->returnCode = $returnCode;
        $this->returnMessage = $returnMessage;
        $this->refundId = $refundId;
        $this->tid = $tid;
        $this->nsu = $nsu;
        $this->refundDateTime = $refundDateTime;
        $this->cancelId = $cancelId;
        $this->amount = $amount;
        $this->status = $status;
        $this->statusHistory = $statusHistory;
        $this->links = $links;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                if ($key === 'links') {
                    if (is_array($value)) {
                        foreach ($value as $linkData) {
                            $this->addLink(new Link(fromData: $linkData));
                        }
                    }
                } elseif ($key === 'statusHistory') {
                    if (is_array($value)) {
                        foreach ($value as $statusData) {
                            $this->addStatus(new Status(fromData: $statusData));
                        }
                    }
                } else {
                    $this->set(key: $key, value: $value);
                }
            }
        }
    }

    public function getReturnCode(): ?string
    {
        return $this->returnCode;
    }

    public function getReturnMessage(): ?string
    {
        return $this->returnMessage;
    }

    public function getRefundId(): ?string
    {
        return $this->refundId;
    }

    public function getTid(): ?string
    {
        return $this->tid;
    }

    public function getNsu(): ?string
    {
        return $this->nsu;
    }

    public function getRefundDateTime(): ?string
    {
        return $this->refundDateTime;
    }

    public function getCancelId(): ?string
    {
        return $this->cancelId;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStatusHistory(): ?array
    {
        return $this->statusHistory;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setReturnCode(?string $returnCode): self
    {
        $this->returnCode = $returnCode;

        return $this;
    }

    public function setReturnMessage(?string $returnMessage): self
    {
        $this->returnMessage = $returnMessage;

        return $this;
    }

    public function setRefundId(?string $refundId): self
    {
        $this->refundId = $refundId;

        return $this;
    }

    public function setTid(?string $tid): self
    {
        $this->tid = $tid;

        return $this;
    }

    public function setNsu(?string $nsu): self
    {
        $this->nsu = $nsu;

        return $this;
    }

    public function setRefundDateTime(?string $refundDateTime): self
    {
        $this->refundDateTime = $refundDateTime;

        return $this;
    }

    public function setCancelId(?string $cancelId): self
    {
        $this->cancelId = $cancelId;

        return $this;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function setStatusHistory(?array $statusHistory): self
    {
        $this->statusHistory = $statusHistory;

        return $this;
    }

    public function setLinks(?array $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function addStatus(Status $status): self
    {
        if (is_null($this->statusHistory)) {
            $this->statusHistory = [];
        }
        $this->statusHistory[] = $status;

        return $this;
    }

    public function addLink(Link $link): self
    {
        if (is_null($this->links)) {
            $this->links = [];
        }
        $this->links[] = $link;

        return $this;
    }
}
