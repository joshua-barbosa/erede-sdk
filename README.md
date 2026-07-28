# eRede SDK

[![Packagist](https://img.shields.io/packagist/v/joshua-barbosa/erede-sdk.svg)](https://packagist.org/packages/joshua-barbosa/erede-sdk)
[![Tests](https://github.com/joshua-barbosa/erede-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/joshua-barbosa/erede-sdk/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/joshua-barbosa/erede-sdk/php.svg)](https://packagist.org/packages/joshua-barbosa/erede-sdk)
[![License](https://img.shields.io/packagist/l/joshua-barbosa/erede-sdk.svg)](LICENSE)

SDK PHP para a API [eRede](https://developer.userede.com.br/e-rede) (Rede) com autenticação OAuth 2.0, integrado ao Laravel.

Suporta **Laravel 11, 12 e 13** / **PHP 8.2+**.

| Laravel | PHP | Status |
|---|---|---|
| 13.x | 8.3 / 8.4 | Testado no CI |
| 12.x | 8.3 | Testado no CI |
| 11.x | 8.2 | Testado no CI — Laravel 11 está EOL, veja a nota abaixo |

> **Laravel 11 está fora do suporte da Laravel** e todas as suas versões carregam
> advisories de segurança. O Composer 2.10+ recusa instalá-lo por padrão; se a sua
> aplicação ainda está em 11.x, ela já precisa lidar com isso independentemente deste
> pacote. Para projetos novos, comece em 12.x ou 13.x.

## Instalação

```bash
composer require joshua-barbosa/erede-sdk
```

O ServiceProvider é descoberto automaticamente — nada a registrar manualmente.

> O pacote está em `0.x`: a API pública ainda pode mudar. Fixe em `^0.1` se quiser
> proteção contra quebras, já que o SemVer permite alterações incompatíveis entre
> versões `0.x` diferentes.

### Desenvolvimento local

Para editar o pacote e ver o efeito imediato na aplicação, use um repositório `path`:

```json
{
    "repositories": [
        { "type": "path", "url": "../erede-sdk", "options": { "symlink": true } }
    ],
    "require": { "joshua-barbosa/erede-sdk": "@dev" }
}
```

Com `symlink: true` o Composer aponta o `vendor/` para a sua cópia local: você edita o pacote e o
efeito é imediato, sem `composer update`.

### Publicar a configuração

```bash
php artisan vendor:publish --tag=erede-config
```

Isso cria `config/erede.php` na aplicação. O pacote funciona sem publicar — os padrões vêm do
próprio arquivo interno —, mas publicar é o caminho para versionar ajustes de timeout, cache e log.

## Configuração

```dotenv
EREDE_MODE=sandbox          # ou production
EREDE_PV=seu-pv
EREDE_TOKEN=sua-chave-de-integracao
```

No OAuth 2.0 da Rede o **PV é o clientId** e a **chave de integração é o clientSecret**.

### Proxy

Ambientes corporativos costumam exigir saída por proxy. Basta definir:

```dotenv
EREDE_PROXY=http://usuario:senha@proxy.empresa.com.br:8080
```

Isso aplica o mesmo proxy a HTTP e HTTPS. Para separar, ou para excluir hosts:

```dotenv
EREDE_PROXY_HTTP=http://proxy.empresa.com.br:8080
EREDE_PROXY_HTTPS=http://proxy.empresa.com.br:8443
EREDE_PROXY_NO=localhost,127.0.0.1,.interno
```

Se nenhuma variável for definida, nenhuma opção de proxy é passada ao Guzzle.

### Timeouts

```dotenv
EREDE_TIMEOUT=60            # resposta completa
EREDE_CONNECT_TIMEOUT=10    # conexão TCP
EREDE_AUTH_TIMEOUT=30       # chamada OAuth
```

Não há retry automático: criar transação é um `POST` não idempotente e uma retentativa cega pode gerar cobrança duplicada. Se precisar de retry, aplique-o apenas nas leituras (`get`, `getByReference`).

### Log

O pacote registra o canal `erede` em `logging.channels` automaticamente (driver `daily`, `storage/logs/erede.log`, retenção de 14 dias) e loga por ele.

```dotenv
EREDE_LOG_CHANNEL=erede     # null → usa o canal padrão da aplicação
EREDE_LOG_ENABLED=true      # false → silencia os logs do SDK
EREDE_LOG_LEVEL=debug
EREDE_LOG_DAYS=14
```

Se você definir `logging.channels.erede` no `config/logging.php` da aplicação, **a sua definição prevalece** — o pacote nunca sobrescreve configuração explícita. Use isso para mandar o eRede para um stack, Slack, Sentry etc.:

```php
// config/logging.php
'channels' => [
    'erede' => [
        'driver' => 'stack',
        'channels' => ['daily', 'slack'],
    ],
],
```

Todo contexto passa por `eRede\Support\Redactor` antes de ir para o log: `access_token`, `securityCode`, `cvv` e afins viram `[REDACTED]`, e `cardNumber` fica mascarado preservando os 4 últimos dígitos.

### Cache do access_token

O token é reaproveitado até expirar, descontando 60 s de margem.

```dotenv
EREDE_CACHE_STORE=redis     # vazio → store padrão da aplicação
```

Com múltiplos workers, prefira um store compartilhado (`redis`, `memcached`). Com `file` ou `array` cada processo autentica por conta própria.

## Uso

### Injeção de dependência (recomendado)

```php
use eRede\eRede;

class CobrancaService
{
    public function __construct(private eRede $erede) {}
}
```

Ou `app(eRede::class)`.

### Instância explícita

Útil quando o PV varia por loja/tenant:

```php
$erede = new eRede(pv: '...', token: '...', env: 'production');
```

Argumentos omitidos caem para o `config/erede.php` — passar só o `pv` mantém timeouts, proxy e cache da aplicação.

### Criar uma transação de crédito

```php
use eRede\Classes\Transaction;

$transaction = (new Transaction(amount: 149.90, reference: 'pedido-1234'))
    ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
    ->setInstallments(3)
    ->setSoftDescriptor('MINHA LOJA');

$response = $erede->transactions()->create($transaction);

$response->getTid();
$response->getReturnCode();     // '00' = aprovada
$response->getAuthorizationCode();
```

### Autorizar agora, capturar depois

```php
$transaction->capture(false);
$autorizada = $erede->transactions()->create($transaction);

// mais tarde
$erede->transactions($autorizada->getTid())->capture(new Amount(149.90));
```

### Consultar

```php
$erede->transactions('30161009000000000001')->get();
$erede->transactions()->getByReference('pedido-1234');
```

### Cancelar / estornar

```php
use eRede\Classes\Amount;
use eRede\Classes\Url;

$erede->transactions($tid)->refunds()->create(
    new Amount(149.90),
    new Url('https://minhaloja.com.br/webhooks/erede'),
);

$erede->transactions($tid)->refunds()->getByTid();
```

## Referência da API

Documentação detalhada de cada tipo, separada por camada:

| Documento | Conteúdo |
|---|---|
| [docs/classes.md](docs/classes.md) | `eRede\Classes` — objetos de entrada que você monta (`Transaction`, `Amount`, `Url`) e os blocos aninhados das respostas (`Authorization`, `Capture`, `Refund`, `Status`, `Link`), além da tabela `ReturnResponse` |
| [docs/components.md](docs/components.md) | `eRede\Components` — `Transactions` e `Refunds`, os verbos do SDK: quais endpoints chamam, como resolvem o `tid` e o que retornam |
| [docs/responses.md](docs/responses.md) | `eRede\Responses` — `Transaction`, `TransactionGet`, `Refund` e `RefundGet`: como a hidratação via `fromData` funciona e o que cada campo significa |
| [docs/traits.md](docs/traits.md) | `eRede\Traits` — `ToArray` (serialização e a regra do `ignoreNullable`), `Attribute` (acesso por nome) e `RetrieveResponse` (resposta HTTP → payload ou exceção) |

## Tratamento de erros

Todas as falhas de comunicação e de negócio lançam `eRede\Exceptions\eRedeException`, que estende `\Exception`.

```php
use eRede\Exceptions\eRedeException;

try {
    $response = $erede->transactions()->create($transaction);
} catch (eRedeException $e) {
    $e->getMessage();   // mensagem da Rede já traduzida
    $e->returnCode();   // ex.: '51' (saldo insuficiente)
    $e->getCode();      // status HTTP
    $e->context();      // dados já sanitizados
}
```

Erros de configuração (credenciais ausentes, ambiente inválido) lançam `ConfigurationException`, que também estende `eRedeException` — capture-a separadamente se quiser distinguir falha de setup de recusa do emissor.

`InvalidArgumentException` continua sendo lançada para uso incorreto da API do SDK (consulta sem `tid`, por exemplo).

## Testes

```bash
composer install
composer test
```

115 testes, 364 asserções. Cobertura: **95,45% de linhas / 92,46% de métodos** (medida com PCOV em PHP 8.3).

A suíte usa `Http::fake()` — nenhuma requisição sai para a Rede.

Para cobertura local:

```bash
vendor/bin/phpunit --coverage-text        # requer pcov ou xdebug
```

Nos testes da sua aplicação, faça o mesmo:

```php
Http::fake([
    '*oauth2/token' => Http::response(['access_token' => 'fake', 'token_type' => 'Bearer', 'expires_in' => 3600]),
    '*/v2/transactions' => Http::response(['tid' => '123', 'returnCode' => '00']),
]);
```

## Corrigido na extração

- **`Responses\RefundGet::getRefunds()` lançava `TypeError` sempre.** A propriedade é `?Refund`, mas getter, setter e construtor declaravam `?string`. Como a hidratação via `fromData` atribui um `Refund`, qualquer chamada a `getRefunds()` após `refunds()->getByTid()` estourava — o fluxo de consulta de estornos estava quebrado de ponta a ponta. Os três tipos passaram a ser `?Refund`.

## Pendências conhecidas

Herdadas da versão original e mantidas para não alterar comportamento nesta extração:

- `Amount::getConvertedAmount()` e `Transaction::setAmount()` usam `ceil()` sobre `float * 100`. Funciona para os valores usuais, mas arredonda para cima; migrar para `intval(round(...))` ou `bcmath` seria mais seguro para dinheiro.
- `Traits\Attribute::set()` recebe 2 parâmetros, mas `Classes\Link`, `Classes\Status` e `Responses\TransactionGet` a chamam com 3. O terceiro é ignorado pelo PHP; é código morto, não um defeito.

## Licença

MIT.
