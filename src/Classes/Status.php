<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

class Status
{
    use Attribute, ToArray;

    private ?string $status = null;

    private ?string $dateTime = null;

    public function __construct(?string $status = null, ?string $dateTime = null, ?array $fromData = null)
    {
        $this->status = $status;
        $this->dateTime = $dateTime;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                $this->set($key, $value, false);
            }
        }
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

    public function getDateTime(): ?string
    {
        return $this->dateTime;
    }

    public function setDateTime(?string $dateTime): self
    {
        $this->dateTime = $dateTime;

        return $this;
    }
}
