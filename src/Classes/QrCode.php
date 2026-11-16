<?php

namespace eRede\Classes;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use eRede\Traits\Attribute;
use eRede\Traits\ToArray;
use InvalidArgumentException;

/**
 * Grupo `qrCode` da solicitação de QR Code Pix.
 *
 * A Rede espera `dateTimeExpiration` no formato YYYY-MM-DDThh:mm:ss, sem
 * offset, com prazo máximo de 15 dias e nunca anterior à data atual.
 */
class QrCode
{
    use Attribute, ToArray;

    public const DATE_FORMAT = 'Y-m-d\TH:i:s';

    /** Fuso assumido pela Rede para a expiração, já que o formato não carrega offset. */
    public const TIMEZONE = 'America/Sao_Paulo';

    private ?string $dateTimeExpiration = null;

    /**
     * @throws InvalidArgumentException
     */
    public function __construct(DateTimeInterface|string $dateTimeExpiration)
    {
        $this->dateTimeExpiration = self::normalize($dateTimeExpiration);
    }

    public function getDateTimeExpiration(): ?string
    {
        return $this->dateTimeExpiration;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function setDateTimeExpiration(DateTimeInterface|string $dateTimeExpiration): self
    {
        $this->dateTimeExpiration = self::normalize($dateTimeExpiration);

        return $this;
    }

    /**
     * Objetos de data são convertidos para o horário de Brasília; strings são
     * enviadas como vieram, para quem precisa controlar o formato exato.
     */
    private static function normalize(DateTimeInterface|string $dateTimeExpiration): string
    {
        if ($dateTimeExpiration instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($dateTimeExpiration)
                ->setTimezone(new DateTimeZone(self::TIMEZONE))
                ->format(self::DATE_FORMAT);
        }

        $dateTimeExpiration = trim($dateTimeExpiration);

        if ($dateTimeExpiration === '') {
            throw new InvalidArgumentException('QrCode expiration date not informed or invalid');
        }

        return $dateTimeExpiration;
    }
}
