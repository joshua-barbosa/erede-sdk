<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

class Authorization
{
    use Attribute, ToArray;

    private ?string $dateTime = '';

    private ?string $returnCode = '';

    private ?string $returnMessage = '';

    private ?int $affiliation = 0;

    private ?string $status = '';

    private ?string $reference = '';

    private ?string $tid = '';

    private ?string $nsu = '';

    private ?string $authorizationCode = '';

    private ?string $kind = '';

    private ?int $amount = 0;

    private ?int $installments = 0;

    private ?string $cardHolderName = '';

    private ?string $cardBin = '';

    private ?string $last4 = '';

    private ?string $softDescriptor = '';

    private ?int $origin = 0;

    private ?bool $subscription = false;

    public function __construct(
        ?string $dateTime = null,
        ?string $returnCode = null,
        ?string $returnMessage = null,
        ?int $affiliation = null,
        ?string $status = null,
        ?string $reference = null,
        ?string $tid = null,
        ?string $nsu = null,
        ?string $authorizationCode = null,
        ?string $kind = null,
        ?int $amount = null,
        ?int $installments = null,
        ?string $cardHolderName = null,
        ?string $cardBin = null,
        ?string $last4 = null,
        ?string $softDescriptor = null,
        ?int $origin = null,
        ?bool $subscription = null,
        ?array $fromData = []
    ) {
        $this->dateTime = $dateTime;
        $this->returnCode = $returnCode;
        $this->returnMessage = $returnMessage;
        $this->affiliation = $affiliation;
        $this->status = $status;
        $this->reference = $reference;
        $this->tid = $tid;
        $this->nsu = $nsu;
        $this->authorizationCode = $authorizationCode;
        $this->kind = $kind;
        $this->amount = $amount;
        $this->installments = $installments;
        $this->cardHolderName = $cardHolderName;
        $this->cardBin = $cardBin;
        $this->last4 = $last4;
        $this->softDescriptor = $softDescriptor;
        $this->origin = $origin;
        $this->subscription = $subscription;

        if (is_array(value: $fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                $this->set(key: $key, value: $value);
            }
        }
    }

    public function getDateTime(): ?string
    {
        return $this->dateTime;
    }

    public function getReturnCode(): ?string
    {
        return $this->returnCode;
    }

    public function getReturnMessage(): ?string
    {
        return $this->returnMessage;
    }

    public function getAffiliation(): ?int
    {
        return $this->affiliation;
    }

    public function getStatus(): ?string
    {
        return $this->status;
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

    public function getAuthorizationCode(): ?string
    {
        return $this->authorizationCode;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getInstallments(): ?int
    {
        return $this->installments;
    }

    public function getCardHolderName(): ?string
    {
        return $this->cardHolderName;
    }

    public function getCardBin(): ?string
    {
        return $this->cardBin;
    }

    public function getLast4(): ?string
    {
        return $this->last4;
    }

    public function getSoftDescriptor(): ?string
    {
        return $this->softDescriptor;
    }

    public function getOrigin(): ?int
    {
        return $this->origin;
    }

    public function isSubscription(): ?bool
    {
        return $this->subscription;
    }

    public function setDateTime(?string $dateTime): self
    {
        $this->dateTime = $dateTime;

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

    public function setAffiliation(?int $affiliation): self
    {
        $this->affiliation = $affiliation;

        return $this;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;

        return $this;
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

    public function setAuthorizationCode(?string $authorizationCode): self
    {
        $this->authorizationCode = $authorizationCode;

        return $this;
    }

    public function setKind(?string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setInstallments(?int $installments): self
    {
        $this->installments = $installments;

        return $this;
    }

    public function setCardHolderName(?string $cardHolderName): self
    {
        $this->cardHolderName = $cardHolderName;

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

    public function setSoftDescriptor(?string $softDescriptor): self
    {
        $this->softDescriptor = $softDescriptor;

        return $this;
    }

    public function setOrigin(?int $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function setSubscription(?bool $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }
}
