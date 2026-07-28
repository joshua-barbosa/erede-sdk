# Referência — `eRede\Traits`

Três traits sustentam o comportamento comum do SDK. Duas são de infraestrutura dos DTOs
(`ToArray` e `Attribute`, usadas por praticamente toda classe de `Classes/` e `Responses/`) e
uma é interna dos componentes (`RetrieveResponse`).

| Trait | Usado por | Papel |
|---|---|---|
| [`ToArray`](#toarray) | Todos os DTOs | Serializa o objeto para o array enviado à Rede |
| [`Attribute`](#attribute) | Todos os DTOs | Acesso genérico por nome; base da hidratação |
| [`RetrieveResponse`](#retrieveresponse) | `Transactions`, `Refunds` | Converte resposta HTTP em payload ou exceção |

---

## ToArray

`eRede\Traits\ToArray` — transforma o DTO no array que vai no corpo da requisição.

É o que roda quando o SDK monta o `POST /v2/transactions`: o
[`Classes\Transaction`](classes.md#transaction) que você montou vira o JSON enviado.

### API pública

```php
public function toArray(bool $ignoreNullable = true, bool $toSnakeCase = false): array
```

### `$ignoreNullable` — o parâmetro que mais importa

Com `true` (padrão), campos "vazios" são **removidos** do array. É o comportamento correto para
a Rede: enviar `"installments": null` provoca erro de validação; simplesmente omitir o campo não.

O critério de descarte é `is_null($v) === false && empty($v) === false`, ou seja, tudo que
`empty()` considera vazio é cortado — **inclusive `0`, `'0'`, `false` e `[]`**.

```php
$t = new Transaction(amount: 149.90, reference: 'pedido-1');
$t->toArray();
// ['reference' => 'pedido-1', 'amount' => 14990]
// nada de cardNumber, kind, installments… — todos null
```

> **Cuidado:** um campo legitimamente `0` ou `false` desaparece do payload. Na prática isso não
> afeta os campos que a Rede usa (`installments` mínimo é 1, `capture` só é enviado quando
> `true`), mas é a regra a ter em mente ao adicionar campos novos.

Com `false`, tudo é preservado, inclusive nulos — útil para depuração e testes:

```php
$t->toArray(ignoreNullable: false);
// ['capture' => null, 'kind' => null, 'reference' => 'pedido-1', ...]
```

### `$toSnakeCase`

Converte as chaves de `camelCase` para `snake_case`:

```php
$t->toArray(toSnakeCase: true);
// ['card_number' => '...', 'expiration_month' => 12, ...]
```

A API da eRede espera **camelCase**, então o padrão é `false` e o SDK nunca liga esse flag
internamente. Existe para quando você precisa do payload noutro formato (log, fila, integração
interna).

A conversão é uma reimplementação de `Str::snake()` — feita à mão de propósito, para o pacote
não depender do comportamento de uma versão específica do `illuminate/support` ao suportar
Laravel 11 a 13.

### Conversão recursiva

`toArray()` desce na estrutura inteira e trata cada tipo:

| Valor | Vira |
|---|---|
| `BackedEnum` | `->value` |
| `UnitEnum` (enum puro) | `->name` |
| Objeto **com** `toArray()` | resultado de `$obj->toArray()`, propagando os dois flags |
| Objeto **sem** `toArray()` | array de suas propriedades públicas |
| Array | array convertido, elemento a elemento |
| Escalar | ele mesmo |

```php
// Url tem toArray(), então vira array aninhado:
['amount' => 14990, 'urls' => [['url' => 'https://...', 'kind' => 'callback']]]
```

### Método interno

```php
private function ObjectArrayToArray(array $objectArray, bool $ignoreNullable = true, bool $toSnakeCase = false): array
```

Motor da recursão. Privado; não faz parte do contrato público.

---

## Attribute

`eRede\Traits\Attribute` — leitura e escrita de propriedades **por nome**, em tempo de execução.

Existe por um motivo específico: hidratar respostas. Quando a Rede devolve
`{"tid": "123", "nsu": "0001"}`, o SDK não sabe em tempo de compilação quais chaves virão —
ele itera o JSON e chama `set($chave, $valor)` para cada uma. Como o trait vive *dentro* da
classe, ele enxerga as propriedades privadas.

### `set()`

```php
public function set(string $key, mixed $value): void
```

Grava `$this->{$key} = $value`.

> **Cria a propriedade se ela não existir.** Isso torna o SDK tolerante a campos novos da API —
> nada quebra quando a Rede adiciona uma chave —, mas o valor só é alcançável via `get()`, já
> que não haverá getter dedicado. Também significa que um erro de digitação não gera erro:
> `set('tidd', '123')` cria `$tidd` silenciosamente.

### `get()`

```php
public function get(string $key): mixed
```

Devolve `$this->{$key} ?? null`. Nunca lança — propriedade inexistente retorna `null`.

```php
$response->get('campoQueAApiPassouAMandar');
```

### `setMany()`

```php
public function setMany(mixed ...$vars): void
```

Escreve vários campos de uma vez, via **argumentos nomeados**. Argumentos posicionais chegam com
chave numérica e são **ignorados** — só o que tem nome é gravado.

```php
$status->setMany(status: 'CONFIRMED', dateTime: '2026-07-28T11:00:00');

$status->setMany('CONFIRMED');  // não faz nada: chave numérica
```

### `getMany()`

```php
public function getMany(mixed ...$vars): array
```

Lê vários campos por nome e devolve um mapa `campo => valor`. Chaves inexistentes são
**omitidas** do resultado, não retornadas como `null`.

```php
$transaction->getMany('reference', 'amount', 'inexistente');
// ['reference' => 'pedido-1', 'amount' => 14990]
```

### Nota sobre chamadas com 3 argumentos

`Classes\Link`, `Classes\Status` e `Responses\TransactionGet` chamam `$this->set($key, $value, false)`
— um argumento a mais do que a assinatura. O PHP ignora argumentos extras em métodos de
userland, então funciona; o terceiro parâmetro é código morto herdado do original, não um defeito.

---

## RetrieveResponse

`eRede\Traits\RetrieveResponse` — ponto único onde a resposta HTTP vira payload ou exceção.

Usado por [`Transactions`](components.md#transactions) e [`Refunds`](components.md#refunds).
Todo método que fala com a Rede passa por aqui, o que concentra num só lugar o tratamento de
erro, o log e a tradução de código de retorno.

### Contrato

A classe que usa o trait precisa expor uma propriedade `$logger` (`?LoggerInterface`). Se ela
for `null`, o trait cai para `NullLogger` — log nunca derruba uma cobrança.

### `retrieveResponse()`

```php
private function retrieveResponse(Illuminate\Http\Client\Response $response): array
```

Fluxo de decisão:

1. **Resposta 2xx** → devolve o JSON decodificado como array. Corpo não-JSON vira `[]`.
2. **Qualquer outra** → registra `error` no canal de log configurado, com o contexto já passado
   por [`Redactor`](../README.md#log), e então:

| Situação | Exceção |
|---|---|
| HTTP 401 | `eRedeException('Não foi possível conectar com o meio de pagamento.')`, código `500` |
| Tem `returnCode` | `eRedeException` com a mensagem traduzida por [`ReturnResponse`](classes.md#returnresponse), código = status HTTP |
| Nenhum dos dois | `eRedeException('Falha na comunicação com o meio de pagamento.')`, código = status HTTP |

Toda exceção carrega a original em `getPrevious()` e o contexto em `context()`.

O 401 recebe tratamento próprio porque significa token inválido ou expirado — problema de
integração, não recusa do emissor. Traduzi-lo como erro de cartão confundiria o diagnóstico.

### `returnCode()`

```php
private function returnCode(array $json): ?string
```

Extrai o código de retorno de onde quer que ele esteja. A Rede o coloca na raiz nas respostas de
criação e dentro de `authorization` nas de consulta:

```php
$json['returnCode'] ?? $json['authorization']['returnCode'] ?? null
```

Sempre devolve string ou `null`, normalizando o tipo.

### `logger()`

```php
private function logger(): LoggerInterface
```

Devolve `$this->logger` ou um `NullLogger`.

### Exemplo de consumo

```php
try {
    $response = $erede->transactions()->create($transaction);
} catch (eRedeException $e) {
    $e->getMessage();   // já traduzido por ReturnResponse
    $e->returnCode();   // '51'
    $e->getCode();      // status HTTP
    $e->context();      // ['status' => 400, 'return_code' => '51']
    $e->getPrevious();  // RequestException original
}
```
