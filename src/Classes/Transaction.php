<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;
use InvalidArgumentException;

class Transaction
{
    use Attribute, ToArray;

    public const CREDIT = 'credit';

    public const DEBIT = 'debit';

    public const ORIGIN_EREDE = 1;

    public const ORIGIN_VISA_CHECKOUT = 4;

    public const ORIGIN_MASTERPASS = 6;

    private ?bool $capture = null;

    private ?string $kind = null;

    private ?string $reference = null;

    private ?int $amount = null;

    private ?int $installments = null;

    private ?string $cardHolderName = null;

    private ?string $cardNumber = null;

    private ?int $expirationMonth = null;

    private ?int $expirationYear = null;

    private ?string $securityCode = null;

    private ?string $softDescriptor = null;

    private ?bool $subscription = null;

    private ?int $origin = null;

    private ?int $distributorAffiliation = null;

    private ?string $brandTid = null;

    public function __construct(?float $amount = null, ?string $reference = null)
    {
        $this->amount = intval(value: ceil(num: $amount * 100));
        $this->reference = $reference;
    }

    public function capture(bool $capture = true): self
    {
        if (! $capture && $this->kind === Transaction::DEBIT) {
            throw new InvalidArgumentException('Debit transactions will always be captured');
        }

        $this->capture = $capture;

        return $this;
    }

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(string $reference): self
    {
        $this->reference = $reference;

        return $this;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function setAmount(float $amount): self
    {
        $this->amount = intval(value: ceil(num: $amount * 100));

        return $this;
    }

    public function getInstallments(): ?int
    {
        return $this->installments;
    }

    public function setInstallments(int $installments): self
    {
        $this->installments = $installments;

        return $this;
    }

    public function getCardHolderName(): ?string
    {
        return $this->cardHolderName;
    }

    public function setCardHolderName(string $cardHolderName): self
    {
        $this->cardHolderName = $cardHolderName;

        return $this;
    }

    public function getCardNumber(): ?string
    {
        return $this->cardNumber;
    }

    public function setCardNumber(string $cardNumber): self
    {
        $this->cardNumber = $cardNumber;

        return $this;
    }

    public function getExpirationMonth(): ?int
    {
        return $this->expirationMonth;
    }

    public function setExpirationMonth(int $expirationMonth): self
    {
        $this->expirationMonth = $expirationMonth;

        return $this;
    }

    public function getExpirationYear(): ?int
    {
        return $this->expirationYear;
    }

    public function setExpirationYear(int $expirationYear): self
    {
        $this->expirationYear = $expirationYear;

        return $this;
    }

    public function getSecurityCode(): ?string
    {
        return $this->securityCode;
    }

    public function setSecurityCode(string $securityCode): self
    {
        $this->securityCode = $securityCode;

        return $this;
    }

    public function getSoftDescriptor(): ?string
    {
        return $this->softDescriptor;
    }

    public function setSoftDescriptor(string $softDescriptor): self
    {
        $this->softDescriptor = $softDescriptor;

        return $this;
    }

    public function getSubscription(): ?bool
    {
        return $this->subscription;
    }

    public function setSubscription(bool $subscription): self
    {
        $this->subscription = $subscription;

        return $this;
    }

    public function getOrigin(): ?int
    {
        return $this->origin;
    }

    public function setOrigin(int $origin): self
    {
        $this->origin = $origin;

        return $this;
    }

    public function getDistributorAffiliation(): ?int
    {
        return $this->distributorAffiliation;
    }

    public function setDistributorAffiliation(int $distributorAffiliation): self
    {
        $this->distributorAffiliation = $distributorAffiliation;

        return $this;
    }

    public function getBrandTid(): ?string
    {
        return $this->brandTid;
    }

    public function setBrandTid(string $brandTid): self
    {
        $this->brandTid = $brandTid;

        return $this;
    }

    public function isCredit(): bool
    {
        return $this->kind === self::CREDIT;
    }

    public function isDebit(): bool
    {
        return $this->kind === self::DEBIT;
    }

    public function isVisaCheckout(): bool
    {
        return $this->origin === self::ORIGIN_VISA_CHECKOUT;
    }

    public function isMasterpass(): bool
    {
        return $this->origin === self::ORIGIN_MASTERPASS;
    }

    public function isErede(): bool
    {
        return $this->origin === self::ORIGIN_EREDE;
    }

    public function isSubscription(): bool
    {
        return $this->subscription === true;
    }

    public function creditCard(string $cardNumber, string $cardCvv, int|string $expirationMonth, int|string $expirationYear, string $holderName): self
    {
        return $this->setCard(
            cardNumber: $cardNumber,
            securityCode: $cardCvv,
            expirationMonth: $expirationMonth,
            expirationYear: $expirationYear,
            cardHolderName: $holderName,
            kind: Transaction::CREDIT
        );
    }

    public function setCard(string $cardNumber, string $securityCode, int|string $expirationMonth, int|string $expirationYear, string $cardHolderName, string $kind): self
    {
        $this->setCardNumber(cardNumber: $cardNumber);
        $this->setSecurityCode(securityCode: $securityCode);
        $this->setExpirationMonth(expirationMonth: $expirationMonth);
        $this->setExpirationYear(expirationYear: $expirationYear);
        $this->setCardHolderName(cardHolderName: $cardHolderName);
        $this->setKind(kind: $kind);

        return $this;
    }

    public function debitCard(string $cardNumber, string $cardCvv, int|string $expirationMonth, int|string $expirationYear, string $holderName): self
    {
        $this->capture();

        return $this->setCard(
            $cardNumber,
            $cardCvv,
            $expirationMonth,
            $expirationYear,
            $holderName,
            Transaction::DEBIT
        );
    }
}
