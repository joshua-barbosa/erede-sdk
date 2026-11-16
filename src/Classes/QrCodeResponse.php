<?php

namespace eRede\Classes;

use eRede\Traits\Attribute;
use eRede\Traits\ToArray;

/**
 * Grupo `qrCodeResponse` das respostas Pix.
 *
 * Na solicitação do QR Code vêm `dateTimeExpiration`, `qrCodeImage` e
 * `qrCodeData`. Na consulta de um QR Code pendente o grupo carrega também os
 * dados da transação (status, tid, amount…) e a expiração vem em
 * `expirationQrCode`.
 */
class QrCodeResponse
{
    use Attribute, ToArray;

    public const STATUS_PENDING = 'Pending';

    public const STATUS_APPROVED = 'Approved';

    public const STATUS_CANCELED = 'Canceled';

    private ?string $dateTime = null;

    private ?string $dateTimeExpiration = null;

    private ?string $expirationQrCode = null;

    private ?string $returnCode = null;

    private ?string $returnMessage = null;

    private ?int $affiliation = null;

    private ?string $kind = null;

    private ?string $reference = null;

    private ?int $amount = null;

    private ?string $tid = null;

    private ?string $status = null;

    private ?string $qrCodeImage = null;

    private ?string $qrCodeData = null;

    public function __construct(
        ?string $dateTime = null,
        ?string $dateTimeExpiration = null,
        ?string $expirationQrCode = null,
        ?string $returnCode = null,
        ?string $returnMessage = null,
        ?int $affiliation = null,
        ?string $kind = null,
        ?string $reference = null,
        ?int $amount = null,
        ?string $tid = null,
        ?string $status = null,
        ?string $qrCodeImage = null,
        ?string $qrCodeData = null,
        ?array $fromData = null
    ) {
        $this->dateTime = $dateTime;
        $this->dateTimeExpiration = $dateTimeExpiration;
        $this->expirationQrCode = $expirationQrCode;
        $this->returnCode = $returnCode;
        $this->returnMessage = $returnMessage;
        $this->affiliation = $affiliation;
        $this->kind = $kind;
        $this->reference = $reference;
        $this->amount = $amount;
        $this->tid = $tid;
        $this->status = $status;
        $this->qrCodeImage = $qrCodeImage;
        $this->qrCodeData = $qrCodeData;

        if (is_array($fromData) && ! empty($fromData)) {
            foreach ($fromData as $key => $value) {
                $this->set(key: $key, value: $value);
            }
        }
    }

    public function getDateTime(): ?string
    {
        return $this->dateTime;
    }

    /** Expiração devolvida na solicitação do QR Code. */
    public function getDateTimeExpiration(): ?string
    {
        return $this->dateTimeExpiration;
    }

    /** Expiração devolvida na consulta de um QR Code pendente. */
    public function getExpirationQrCode(): ?string
    {
        return $this->expirationQrCode;
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

    public function getKind(): ?string
    {
        return $this->kind;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function getAmount(): ?int
    {
        return $this->amount;
    }

    public function getTid(): ?string
    {
        return $this->tid;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    /** Imagem do QR Code em base64. */
    public function getQrCodeImage(): ?string
    {
        return $this->qrCodeImage;
    }

    /** Código EMV do QR Code (Pix copia e cola). */
    public function getQrCodeData(): ?string
    {
        return $this->qrCodeData;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isCanceled(): bool
    {
        return $this->status === self::STATUS_CANCELED;
    }
}
