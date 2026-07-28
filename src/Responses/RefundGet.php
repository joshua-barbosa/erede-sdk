<?php

namespace eRede\Responses;

use eRede\Classes\Link;
use eRede\Classes\Refund;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property Refund|null $refunds
 * @property array<int,Link>|null $links
 */
class RefundGet
{
    use Attribute, ToArray;

    private ?Refund $refunds = null;

    private ?array $links = null;

    public function __construct(
        ?Refund $refunds = null,
        ?array $links = null,
        ?array $fromData = null
    ) {
        $this->refunds = $refunds;
        $this->links = $links;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                if ($key === 'links') {
                    if (is_array($value)) {
                        foreach ($value as $linkData) {
                            $this->addLink(new Link(fromData: $linkData));
                        }
                    }
                } elseif ($key === 'refunds') {
                    if (is_array($value)) {
                        $this->refunds = new Refund(fromData: $value);
                    } else {
                        $this->refunds = new Refund(fromData: []);
                    }
                } else {
                    $this->set(key: $key, value: $value);
                }
            }
        }
    }

    public function getRefunds(): ?Refund
    {
        return $this->refunds;
    }

    public function setRefunds(?Refund $refunds): self
    {
        $this->refunds = $refunds;

        return $this;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setLinks(?array $links): self
    {
        $this->links = $links;

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
