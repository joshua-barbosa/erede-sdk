<?php

namespace eRede;

use eRede\Components\Transactions;
use eRede\Exceptions\eRedeException;
use eRede\Support\Config;
use eRede\Support\HttpOptions;
use eRede\Support\Logger;
use eRede\Support\Redactor;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cliente da API eRede.
 *
 * Pode ser resolvido pelo container (usando config/erede.php) ou instanciado
 * diretamente com credenciais explícitas:
 *
 *     $erede = app(eRede::class);
 *     $erede = new eRede(pv: '...', token: '...', env: 'production');
 */
class eRede
{
    public const ALLOWED_METHODS = ['GET', 'POST', 'PUT', 'PATCH', 'OPTIONS', 'DELETE'];

    public const URL = [
        'production' => 'https://api.userede.com.br/erede',
        'sandbox' => 'https://sandbox-erede.useredecloud.com.br',
        'sandbox-oauth' => 'https://rl7-sandbox-api.useredecloud.com.br/oauth2/token',
        'production-oauth' => 'https://api.userede.com.br/redelabs/oauth2/token',
    ];

    /** Tipo de concessão fixo exigido pelo OAuth 2.0 da Rede. */
    public const GRANT_TYPE = 'client_credentials';

    /** Validade assumida (1440 s = 24 min) quando a Rede não devolve `expires_in`. */
    public const TOKEN_DEFAULT_EXPIRES_IN = 1440;

    /** Margem em segundos descontada da validade para não usar token prestes a expirar. */
    public const TOKEN_EXPIRATION_SKEW = 60;

    private readonly Config $config;

    private readonly ?Container $container;

    private ?LoggerInterface $logger = null;

    /**
     * Os três primeiros argumentos sobrescrevem pontualmente o que veio de
     * config/erede.php — passar apenas o `pv`, por exemplo, mantém timeouts,
     * proxy e cache configurados na aplicação.
     *
     * @throws Exceptions\ConfigurationException
     */
    public function __construct(
        ?string $pv = null,
        ?string $token = null,
        ?string $env = null,
        ?Config $config = null,
        ?Container $container = null,
    ) {
        $this->container = $container ?? (function_exists('app') ? app() : null);

        $config ??= $this->configFromContainer();

        $this->config = $config->with(pv: $pv, token: $token, env: $env)->validate();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function transactions(?string $tid = null): Transactions
    {
        return new Transactions($this->request(), $tid, $this->logger());
    }

    /** Descarta o access_token em cache, forçando nova autenticação. */
    public function forgetAccessToken(): void
    {
        $this->cache()->forget($this->config->cacheKey());
    }

    private function configFromContainer(): Config
    {
        if ($this->container?->bound(Config::class)) {
            return $this->container->make(Config::class);
        }

        $values = $this->container?->bound('config')
            ? (array) $this->container->make('config')->get('erede', [])
            : [];

        return Config::fromArray($values);
    }

    private function url(): string
    {
        return self::URL[$this->config->isProduction() ? 'production' : 'sandbox'];
    }

    private function oauthUrl(): string
    {
        return self::URL[$this->config->isProduction() ? 'production-oauth' : 'sandbox-oauth'];
    }

    /** @return array<string,mixed> */
    private function options(): array
    {
        return HttpOptions::fromConfig($this->config);
    }

    private function logger(): LoggerInterface
    {
        return $this->logger ??= Logger::resolve($this->config, $this->container);
    }

    private function http(): HttpFactory
    {
        if ($this->container?->bound(HttpFactory::class)) {
            return $this->container->make(HttpFactory::class);
        }

        return new HttpFactory;
    }

    private function cache(): CacheRepository
    {
        $factory = $this->container?->bound('cache')
            ? $this->container->make('cache')
            : null;

        if (! $factory instanceof CacheFactory) {
            throw new eRedeException(
                'eRede: nenhum cache disponível para armazenar o access_token.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
            );
        }

        return $factory->store($this->config->cacheStore());
    }

    /**
     * Autentica no padrão OAuth 2.0 (client_credentials): o PV é o clientId e a
     * chave de integração é o clientSecret.
     *
     * @return array{access_token:string,token_type:string,expires_in:int,scope:string}
     *
     * @throws eRedeException
     */
    private function authenticate(): array
    {
        $response = $this->http()
            ->asForm()
            ->withBasicAuth($this->config->pv, $this->config->token)
            ->withOptions($this->options())
            ->timeout($this->config->authTimeout())
            ->acceptJson()
            ->post($this->oauthUrl(), ['grant_type' => self::GRANT_TYPE]);

        $data = $response->json();
        $data = is_array($data) ? $data : [];

        if ($response->failed() || ! ($data['access_token'] ?? null)) {
            $this->logger()->error('eRede OAuth 2.0 authentication failed.', Redactor::scrub([
                'env' => $this->config->env,
                'status' => $response->status(),
                'return' => $data,
            ]));

            throw new eRedeException(
                'Não foi possível conectar com o meio de pagamento.',
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $response->toException(),
                ['status' => $response->status()],
            );
        }

        $expiresIn = (int) ($data['expires_in'] ?? self::TOKEN_DEFAULT_EXPIRES_IN);

        $credentials = [
            'access_token' => (string) $data['access_token'],
            'token_type' => (string) ($data['token_type'] ?? 'Bearer'),
            'expires_in' => $expiresIn,
            'scope' => (string) ($data['scope'] ?? ''),
        ];

        $ttl = max(self::TOKEN_EXPIRATION_SKEW, $expiresIn - self::TOKEN_EXPIRATION_SKEW);

        $this->cache()->put($this->config->cacheKey(), $credentials, $ttl);

        return $credentials;
    }

    /**
     * Recupera o access_token vigente, gerando um novo sempre que o anterior
     * expirar.
     *
     * @return array{access_token:string,token_type:string,expires_in:int,scope:string}
     */
    private function credentials(): array
    {
        $credentials = $this->cache()->get($this->config->cacheKey());

        if (is_array($credentials) && ($credentials['access_token'] ?? null)) {
            return $credentials;
        }

        return $this->authenticate();
    }

    private function request(): PendingRequest
    {
        $credentials = $this->credentials();

        return $this->http()
            ->withHeaders([
                'X-XSS-Protection' => '1',
                'Strict-Transport-Security' => 'max-age=31536000',
                'Access-Control-Allow-Origin' => '*',
                'Access-Control-Allow-Methods' => implode(',', self::ALLOWED_METHODS),
                'Access-Control-Allow-Headers' => '*',
                'Access-Control-Max-Age' => '31536000',
            ])
            ->withToken($credentials['access_token'], $credentials['token_type'])
            ->withOptions($this->options())
            ->timeout($this->config->timeout())
            ->acceptJson()
            ->baseUrl($this->url());
    }
}
