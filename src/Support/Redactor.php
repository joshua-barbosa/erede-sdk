<?php

namespace eRede\Support;

/**
 * Remove dados sensíveis do contexto antes de escrever no log.
 *
 * Um SDK de pagamento nunca deve deixar PAN, CVV ou access_token cair em
 * arquivo de log — isso vale inclusive nos payloads de erro devolvidos pela
 * própria Rede.
 */
final class Redactor
{
    public const MASK = '[REDACTED]';

    /**
     * Chaves cujo valor é apagado por completo.
     *
     * `authorization` fica de fora de propósito: nas respostas de consulta ela
     * é o objeto que carrega returnCode e status — mascarar apagaria justamente
     * o que se quer diagnosticar no log.
     */
    private const SENSITIVE = [
        'access_token',
        'refresh_token',
        'securitycode',
        'cvv',
        'cvc',
        'password',
        'secret',
        'client_secret',
        'token',
    ];

    /** Chaves que preservam apenas os últimos dígitos. */
    private const PARTIAL = [
        'cardnumber',
        'card_number',
        'pan',
    ];

    /**
     * @param  array<mixed>  $context
     * @return array<mixed>
     */
    public static function scrub(array $context): array
    {
        $clean = [];

        foreach ($context as $key => $value) {
            $normalized = is_string($key) ? strtolower(str_replace(['-', ' '], '_', $key)) : '';

            if ($normalized !== '' && in_array($normalized, self::SENSITIVE, true)) {
                $clean[$key] = self::MASK;

                continue;
            }

            if ($normalized !== '' && in_array($normalized, self::PARTIAL, true)) {
                $clean[$key] = self::maskPan($value);

                continue;
            }

            $clean[$key] = is_array($value) ? self::scrub($value) : $value;
        }

        return $clean;
    }

    private static function maskPan(mixed $value): string
    {
        $digits = preg_replace('/\D/', '', (string) $value) ?? '';

        if (strlen($digits) < 4) {
            return self::MASK;
        }

        return str_repeat('*', max(0, strlen($digits) - 4)).substr($digits, -4);
    }
}
