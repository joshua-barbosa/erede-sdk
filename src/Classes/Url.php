<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property string $url
 * @property string $kind
 *
 * @method string getUrl()
 * @method string getKind()
 * @method Url setUrl(string $url)
 * @method Url setKind(string $kind)
 */
class Url
{
    use Attribute, ToArray;

    public const CALLBACK = 'callback';

    public const THREE_D_SECURE_FAILURE = 'threeDSecureFailure';

    public const THREE_D_SECURE_SUCCESS = 'threeDSecureSuccess';

    public function __construct(private string $url, private string $kind = self::CALLBACK) {}

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getKind(): string
    {
        return $this->kind;
    }

    public function setKind(string $kind): self
    {
        $this->kind = $kind;

        return $this;
    }
}
