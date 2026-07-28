<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

class Link
{
    use Attribute, ToArray;

    private ?string $method = null;

    private ?string $rel = null;

    private ?string $href = null;

    public function __construct(?string $method = null, ?string $rel = null, ?string $href = null, ?array $fromData = [])
    {
        $this->method = $method;
        $this->rel = $rel;
        $this->href = $href;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                $this->set($key, $value, false);
            }
        }
    }

    public function getMethod(): ?string
    {
        return $this->method;
    }

    public function getRel(): ?string
    {
        return $this->rel;
    }

    public function getHref(): ?string
    {
        return $this->href;
    }

    public function setMethod(?string $method): self
    {
        $this->method = $method;

        return $this;
    }

    public function setRel(?string $rel): self
    {
        $this->rel = $rel;

        return $this;
    }

    public function setHref(?string $href): self
    {
        $this->href = $href;

        return $this;
    }
}
