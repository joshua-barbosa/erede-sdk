<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

class Capture
{
    use Attribute, ToArray;

    private ?string $dateTime = '';

    private ?string $nsu = '';

    private ?int $amount = 0;

    private ?string $brandTid = '';

    public function __construct(
        ?string $dateTime = null,
        ?string $nsu = null,
        ?int $amount = null,
        ?string $brandTid = null,
        ?array $fromData = []
    ) {
        $this->dateTime = $dateTime;
        $this->nsu = $nsu;
        $this->amount = $amount;
        $this->brandTid = $brandTid;

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

    public function getNsu(): ?string
    {
        return $this->nsu;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getBrandTid(): ?string
    {
        return $this->brandTid;
    }

    public function setDateTime(?string $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }

    public function setNsu(?string $nsu): self
    {
        $this->nsu = $nsu;

        return $this;
    }

    public function setAmount(?int $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function setBrandTid(?string $brandTid): self
    {
        $this->brandTid = $brandTid;

        return $this;
    }
}
