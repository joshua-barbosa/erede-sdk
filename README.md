# eRede SDK

[![Packagist](https://img.shields.io/packagist/v/joshua-barbosa/erede-sdk.svg)](https://packagist.org/packages/joshua-barbosa/erede-sdk)
[![Tests](https://github.com/joshua-barbosa/erede-sdk/actions/workflows/tests.yml/badge.svg)](https://github.com/joshua-barbosa/erede-sdk/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/joshua-barbosa/erede-sdk/php.svg)](https://packagist.org/packages/joshua-barbosa/erede-sdk)
[![License](https://img.shields.io/packagist/l/joshua-barbosa/erede-sdk.svg)](LICENSE)
[![Buy Me A Coffee](https://img.shields.io/badge/buy%20me%20a%20coffee-%E2%98%95-FFDD00)](https://www.buymeacoffee.com/joshuabarbosa)

SDK PHP para a API [eRede](https://developer.userede.com.br/e-rede) (Rede) com autenticação OAuth 2.0, integrado ao Laravel.

Suporta **Laravel 8 a 13** / **PHP 8.0+**.

## Compatibilidade

| Laravel | PHP | Testado no CI | Situação |
|---|---|---|---|
| 13.x | 8.3 · 8.4 | ✅ | Recomendado |
| 12.x | 8.3 | ✅ | Recomendado |
| 11.x | 8.2 | ✅ | ⚠️ Suporte a ser removido |
| 10.x | 8.1 | ✅ | ⚠️ Suporte a ser removido |
| 9.x | 8.1 | ✅ | ⚠️ Suporte a ser removido |
| 8.x | 8.0 | ✅ | ⚠️ Suporte a ser removido |

> ### ⚠️ Laravel 8, 9, 10 e 11 serão descontinuados
>
> O suporte a essas versões existe como **medida paliativa**, para que projetos
> legados consigam usar o SDK enquanto a migração não acontece. Ele **será removido
> numa versão futura**, sem prazo definido mas sem cerimônia — provavelmente na
> primeira versão que precisar de um recurso mais novo do framework.
>
> Todas as quatro já estão **fora do suporte oficial da Laravel** e carregam
> advisories de segurança conhecidas. Na prática isso significa que o Composer 2.10+
> **recusa instalá-las por padrão** — se o seu projeto está numa delas, você já
> convive com esse bloqueio, independentemente deste pacote.
>
> **Migre para o Laravel 12 ou 13.** Enquanto isso não é viável, fixe a versão do
> SDK para não ser surpreendido quando o suporte cair:
>
> ```bash
> composer require joshua-barbosa/erede-sdk:~0.2.0
> ```
>
> O `~0.2.0` aceita correções (`0.2.1`, `0.2.2`) mas não sobe para `0.3.0`, que é
> onde a remoção pode acontecer.

O piso de PHP 8.0 custou alguns recursos de 8.1+ (`readonly`, `enum` nos testes).
O que foi cedido e como reverter está em [docs/php-8.0-compat.md](docs/php-8.0-compat.md).

## Instalação

```bash
composer require joshua-barbosa/erede-sdk
```

O ServiceProvider é descoberto automaticamente — nada a registrar manualmente.

> O pacote está em `0.x`: a API pública ainda pode mudar. O SemVer permite alterações
> incompatíveis entre versões `0.x` diferentes, então fixe em `~0.2.0` se quiser
> proteção contra quebras — sobretudo se você depende do suporte a Laravel 8–11.

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

> **Desenvolver o pacote exige PHP 8.1+**, embora ele *rode* em 8.0. O motivo é o
> `laravel/pint`, que exige 8.1. Para instalar as dependências de desenvolvimento em
> PHP 8.0, remova-o antes: `composer remove --dev laravel/pint --no-update`. É o que
> a linha de PHP 8.0 do CI faz.

99 testes, 364 asserções. Cobertura: **94,94% de linhas / 92,46% de métodos** (medida com PCOV em PHP 8.3).

A suíte usa `Http::fake()` — nenhuma requisição sai para a Rede.

Para cobertura local:

```bash
vendor/bin/phpunit --coverage-text --coverage-filter src   # requer pcov ou xdebug
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

## Apoie

Este pacote é mantido nas horas vagas. Se ele te poupou algumas horas de briga com
a API da Rede, [me paga um café](https://www.buymeacoffee.com/joshuabarbosa) ☕ — ajuda
a manter a compatibilidade em dia conforme o Laravel e a API da Rede evoluem.

Contribuição de código também é bem-vinda: abra uma
[issue](https://github.com/joshua-barbosa/erede-sdk/issues) ou um PR.

## Licença

MIT.
