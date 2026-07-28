<?php

namespace eRede\Responses;

use eRede\Classes\Authorization;
use eRede\Classes\Capture;
use eRede\Classes\Link;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property Authorization|null $authorization
 * @property Capture|null $capture
 * @property array<int,Link>|null $links
 */
class TransactionGet
{
    use Attribute, ToArray;

    public ?Authorization $authorization = null;

    public ?Capture $capture = null;

    public ?array $links = null;

    public function __construct(
        ?Authorization $authorization = null,
        ?Capture $capture = null,
        ?array $links = null,
        ?array $fromData = null
    ) {
        $this->authorization = $authorization;
        $this->capture = $capture;
        $this->links = $links;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                if ($key === 'links') {
                    if (is_array($value)) {
                        foreach ($value as $linkData) {
                            $this->addLink(new Link(fromData: $linkData));
                        }
                    }
                } elseif ($key === 'authorization') {
                    $this->setAuthorization(new Authorization(fromData: $value));
                } elseif ($key === 'capture') {
                    $this->setCapture(new Capture(fromData: $value));
                } else {
                    $this->set($key, $value, false);
                }
            }
        }
    }

    public function getAuthorization(): ?Authorization
    {
        return $this->authorization;
    }

    public function getCapture(): ?Capture
    {
        return $this->capture;
    }

    public function getLinks(): ?array
    {
        return $this->links;
    }

    public function setAuthorization(?Authorization $authorization): void
    {
        $this->authorization = $authorization;
    }

    public function setCapture(?Capture $capture): void
    {
        $this->capture = $capture;
    }

    public function setLinks(?array $links): void
    {
        $this->links = $links;
    }

    public function addLink(Link $link): void
    {
        if ($this->links === null) {
            $this->links = [];
        }
        $this->links[] = $link;
    }
}
