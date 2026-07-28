<?php

namespace eRede\Exceptions;

/**
 * Lançada quando o SDK é usado sem credenciais válidas ou com um ambiente
 * desconhecido. Indica erro de configuração, não falha de comunicação.
 */
class ConfigurationException extends eRedeException
{
    public static function missingCredentials(): self
    {
        return new self(
            'eRede: credenciais ausentes. Defina EREDE_PV e EREDE_TOKEN no .env '
            .'ou passe pv/token explicitamente ao construtor.'
        );
    }

    public static function invalidEnvironment(string $env): self
    {
        return new self(
            sprintf('eRede: ambiente "%s" inválido. Use "sandbox" ou "production".', $env)
        );
    }
}
