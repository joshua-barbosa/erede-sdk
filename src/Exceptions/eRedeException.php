<?php

namespace eRede\Exceptions;

use Exception;
use Throwable;

/**
 * Exceção base do SDK.
 *
 * Estende \Exception para manter compatibilidade com quem já captura
 * genericamente as falhas do pacote.
 */
class eRedeException extends Exception
{
    /** @var array<string,mixed> */
    private array $context;

    /**
     * @param  array<string,mixed>  $context
     */
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null, array $context = [])
    {
        parent::__construct($message, $code, $previous);

        $this->context = $context;
    }

    /** @return array<string,mixed> */
    public function context(): array
    {
        return $this->context;
    }

    /** Código de retorno devolvido pela Rede, quando houver. */
    public function returnCode(): ?string
    {
        $code = $this->context['return_code'] ?? null;

        return $code === null ? null : (string) $code;
    }
}
