<?php

namespace eRede\Classes;

class Amount
{
    public function __construct(private float $amount) {}

    public function setAmount(float $amount): self
    {
        $this->amount = $amount;

        return $this;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getConvertedAmount(): int
    {
        return intval(value: ceil(num: $this->amount * 100));
    }
}
