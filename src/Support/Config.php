<?php

namespace eRede\Support;

use eRede\Exceptions\ConfigurationException;

/**
 * Configuração imutável do SDK.
 *
 * Toda alteração devolve uma nova instância — nenhum método muda o objeto
 * existente.
 */
final class Config
{
    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    public const ENVIRONMENTS = [self::ENV_SANDBOX, self::ENV_PRODUCTION];

    /** Valores usados quando a chave não vem do arquivo de configuração. */
    private const DEFAULTS = [
        'http' => [
            'proxy' => [],
            'timeout' => 60,
            'connect_timeout' => 10,
            'auth_timeout' => 30,
            'verify' => true,
        ],
        'cache' => [
            'store' => null,
            'prefix' => 'erede.access_token',
        ],
        'logging' => [
            'enabled' => true,
            'channel' => 'erede',
        ],
    ];

    /**
     * @param  array<string,mixed>  $http
     * @param  array<string,mixed>  $cache
     * @param  array<string,mixed>  $logging
     */
    public function __construct(
        public readonly ?string $pv = null,
        public readonly ?string $token = null,
        public readonly string $env = self::ENV_SANDBOX,
        public readonly array $http = self::DEFAULTS['http'],
        public readonly array $cache = self::DEFAULTS['cache'],
        public readonly array $logging = self::DEFAULTS['logging'],
    ) {}

    /**
     * Monta a configuração a partir do array publicado em config/erede.php.
     *
     * @param  array<string,mixed>  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            pv: self::nullableString($config['pv'] ?? null),
            token: self::nullableString($config['token'] ?? null),
            env: self::nullableString($config['mode'] ?? null) ?? self::ENV_SANDBOX,
            http: array_replace(self::DEFAULTS['http'], is_array($config['http'] ?? null) ? $config['http'] : []),
            cache: array_replace(self::DEFAULTS['cache'], is_array($config['cache'] ?? null) ? $config['cache'] : []),
            logging: array_replace(self::DEFAULTS['logging'], is_array($config['logging'] ?? null) ? $config['logging'] : []),
        );
    }

    /**
     * Devolve uma cópia com os valores informados sobrescritos.
     *
     * Argumentos nulos ou vazios são ignorados, então chamadas parciais
     * (só o pv, por exemplo) preservam o resto da configuração.
     */
    public function with(?string $pv = null, ?string $token = null, ?string $env = null): self
    {
        return new self(
            pv: self::nullableString($pv) ?? $this->pv,
            token: self::nullableString($token) ?? $this->token,
            env: self::nullableString($env) ?? $this->env,
            http: $this->http,
            cache: $this->cache,
            logging: $this->logging,
        );
    }

    /**
     * @throws ConfigurationException
     */
    public function validate(): self
    {
        if ($this->pv === null || $this->token === null) {
            throw ConfigurationException::missingCredentials();
        }

        if (! in_array($this->env, self::ENVIRONMENTS, true)) {
            throw ConfigurationException::invalidEnvironment($this->env);
        }

        return $this;
    }

    public function isProduction(): bool
    {
        return $this->env === self::ENV_PRODUCTION;
    }

    /** Chave de cache do access_token, isolada por ambiente e por PV. */
    public function cacheKey(): string
    {
        $prefix = $this->cache['prefix'] ?? self::DEFAULTS['cache']['prefix'];

        return $prefix.'.'.$this->env.'.'.sha1((string) $this->pv);
    }

    public function cacheStore(): ?string
    {
        return self::nullableString($this->cache['store'] ?? null);
    }

    public function timeout(): int
    {
        return max(1, (int) ($this->http['timeout'] ?? self::DEFAULTS['http']['timeout']));
    }

    public function connectTimeout(): int
    {
        return max(1, (int) ($this->http['connect_timeout'] ?? self::DEFAULTS['http']['connect_timeout']));
    }

    public function authTimeout(): int
    {
        return max(1, (int) ($this->http['auth_timeout'] ?? self::DEFAULTS['http']['auth_timeout']));
    }

    /**
     * Trata string vazia ou só com espaços como ausência de valor — o
     * construtor original usava '' como padrão e env() devolve '' com
     * frequência.
     */
    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return $value === null ? null : (string) $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
