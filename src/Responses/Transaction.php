<?php

namespace eRede\Responses;

use eRede\Classes\Link;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

class Transaction
{
    use Attribute, ToArray;

    public ?string $reference = '';

    public ?string $tid = '';

    public ?string $nsu = '';

    public ?string $brandTid = '';

    public ?string $authorizationCode = '';

    public ?string $dateTime = '';

    public ?int $amount = 0;

    public ?string $cardBin = '';

    public ?string $last4 = '';

    public ?string $returnCode = '';

    public ?string $returnMessage = '';

    public ?array $links = null;

    public function __construct(
        ?string $reference = null,
        ?string $tid = null,
        ?string $nsu = null,
        ?string $brandTid = null,
        ?string $authorizationCode = null,
        ?string $dateTime = null,
        ?int $amount = null,
        ?string $cardBin = null,
        ?string $last4 = null,
        ?string $returnCode = null,
        ?string $returnMessage = null,
        ?array $links = null,
        ?array $fromData = null
    ) {
        $this->reference = $reference;
        $this->tid = $tid;
        $this->nsu = $nsu;
        $this->brandTid = $brandTid;
        $this->authorizationCode = $authorizationCode;
        $this->dateTime = $dateTime;
        $this->amount = $amount;
        $this->cardBin = $cardBin;
        $this->last4 = $last4;
        $this->returnCode = $returnCode;
        $this->returnMessage = $returnMessage;
        $this->links = $links;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                if ($key === 'links') {
                    if (is_array($value)) {
                        foreach ($value as $linkData) {
                            $this->addLink(new Link(fromData: $linkData));
                        }
                    }
                } else {
                    $this->set($key, value: $value);
                }
            }
        }
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getTid(): ?string
    {
        return $this->tid;
    }

    public function getNsu(): ?string
    {
        return $this->nsu;
    }

    public function getBrandTid(): ?string
    {
        return $this->brandTid;
    }

    public function getAuthorizationCode(): ?string
    {
        return $this->authorizationCode;
    }

    public function getDateTime(): ?string
    {
        return $this->dateTime;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getCardBin(): ?string
    {
        return $this->cardBin;
    }

    public function getLast4(): ?string
    {
        return $this->last4;
    }

    public function getReturnCode(): ?string
    {
        return $this->returnCode;
    }

    public function getReturnMessage(): ?string
    {
        return $this->returnMessage;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;

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

    public function setBrandTid(?string $brandTid): self
    {
        $this->brandTid = $brandTid;

        return $this;
    }

    public function setAuthorizationCode(?string $authorizationCode): self
    {
        $this->authorizationCode = $authorizationCode;

        return $this;
    }

    public function setDateTime(?string $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setCardBin(?string $cardBin): self
    {
        $this->cardBin = $cardBin;

        return $this;
    }

    public function setLast4(?string $last4): self
    {
        $this->last4 = $last4;

        return $this;
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

    public function setLinks(?array $links): self
    {
        $this->links = $links;

        return $this;
    }

    public function addLink(Link $link): self
    {
        if ($this->links === null) {
            $this->links = [];
        }
        $this->links[] = $link;

        return $this;
    }
}
