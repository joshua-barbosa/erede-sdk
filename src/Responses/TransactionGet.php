<?php

namespace eRede\Responses;

use eRede\Classes\Authorization;
use eRede\Classes\Capture;
use eRede\Classes\Link;
use eRede\Classes\QrCodeResponse;
use eRede\Classes\Refund;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * @property Authorization|null $authorization
 * @property Capture|null $capture
 * @property array<int,Link>|null $links
 * @property string|null $requestDateTime
 * @property QrCodeResponse|null $qrCodeResponse
 * @property array<int,Refund>|null $refunds
 */
class TransactionGet
{
    use Attribute, ToArray;

    public ?Authorization $authorization = null;

    public ?Capture $capture = null;

    public ?array $links = null;

    public ?string $requestDateTime = null;

    /** Presente apenas em QR Code Pix ainda pendente. */
    public ?QrCodeResponse $qrCodeResponse = null;

    /** Devoluções atreladas à transação (Pix pago ou devolvido). */
    public ?array $refunds = null;

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
                } elseif ($key === 'qrCodeResponse') {
                    $this->setQrCodeResponse(is_array($value) ? new QrCodeResponse(fromData: $value) : null);
                } elseif ($key === 'refunds') {
                    $this->setRefunds($this->hydrateRefunds($value));
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

    /**
     * A Rede devolve `refunds` como lista; um objeto único também é aceito.
     *
     * @return array<int,Refund>
     */
    private function hydrateRefunds(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $isList = $value === [] || array_keys($value) === range(0, count($value) - 1);

        return array_map(
            static fn ($refundData) => new Refund(fromData: is_array($refundData) ? $refundData : []),
            $isList ? $value : [$value],
        );
    }

    public function getRequestDateTime(): ?string
    {
        return $this->requestDateTime;
    }

    public function setRequestDateTime(?string $requestDateTime): void
    {
        $this->requestDateTime = $requestDateTime;
    }

    public function getQrCodeResponse(): ?QrCodeResponse
    {
        return $this->qrCodeResponse;
    }

    public function setQrCodeResponse(?QrCodeResponse $qrCodeResponse): void
    {
        $this->qrCodeResponse = $qrCodeResponse;
    }

    /** @return array<int,Refund>|null */
    public function getRefunds(): ?array
    {
        return $this->refunds;
    }

    /** @param  array<int,Refund>|null  $refunds */
    public function setRefunds(?array $refunds): void
    {
        $this->refunds = $refunds;
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
