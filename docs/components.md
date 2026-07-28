# Referência — `eRede\Components`

Os componentes são os **verbos** do SDK: cada um encapsula um grupo de endpoints da Rede.
Você nunca os instancia diretamente — eles saem de `eRede::transactions()`, já com o
`PendingRequest` autenticado e o logger do canal configurado.

```php
$erede = app(eRede::class);

$erede->transactions();          // Transactions
$erede->transactions($tid);      // Transactions já com o tid
$erede->transactions($tid)->refunds();  // Refunds herdando o tid
```

| Componente | Base URL | Origem |
|---|---|---|
| [`Transactions`](#transactions) | `/v2/transactions` | `eRede::transactions()` |
| [`Refunds`](#refunds) | `/v1/transactions/{tid}/refunds` | `Transactions::refunds()` |

Todos os métodos que falam com a Rede lançam
[`eRede\Exceptions\eRedeException`](../README.md#tratamento-de-erros) em erro de negócio ou
comunicação, e `InvalidArgumentException` em uso incorreto do SDK (tid ausente, por exemplo).

---

## Transactions

`eRede\Components\Transactions`

### Constante

| Constante | Valor |
|---|---|
| `BASE_URL` | `'/v2/transactions'` |

### Construtor

```php
public function __construct(
    PendingRequest $request,
    ?string $tid = null,
    ?LoggerInterface $logger = null,
)
```

Chamado pelo `eRede`; o `$request` já vem com `Authorization: Bearer`, `baseUrl`, timeouts e
proxy aplicados.

### `create()`

```php
public function create(Classes\Transaction $transaction): Responses\Transaction
```

`POST /v2/transactions` — cria (autoriza e, por padrão, captura) uma transação.

```php
$response = $erede->transactions()->create($transaction);
$response->getTid();
$response->getReturnCode();  // '00' = aprovada
```

> **Não é idempotente.** O SDK não faz retry automático justamente por isso — uma
> retentativa cega pode gerar cobrança duplicada.

### `get()`

```php
public function get(?string $tid = null): Responses\TransactionGet
```

`GET /v2/transactions/{tid}` — consulta por TID. Usa o `$tid` do argumento ou o do componente.
Lança `InvalidArgumentException('Transactions id not informed or invalid')` se nenhum dos dois existir.

```php
$erede->transactions('30161009000000000001')->get();
$erede->transactions()->get('30161009000000000001');  // equivalente
```

### `getByReference()`

```php
public function getByReference(string $reference): Responses\TransactionGet
```

`GET /v2/transactions?reference={reference}` — consulta pela sua referência de pedido.

### `capture()`

```php
public function capture(Classes\Amount $amount, ?string $tid = null): Responses\Transaction
```

`PUT /v2/transactions/{tid}` — captura uma transação previamente autorizada com
`Transaction::capture(false)`. O valor vai convertido em centavos.

```php
$transaction->capture(false);
$autorizada = $erede->transactions()->create($transaction);

// depois
$erede->transactions($autorizada->getTid())->capture(new Amount(149.90));
```

Mesma regra de resolução de `$tid` do `get()`.

### `refunds()`

```php
public function refunds(?string $tid = null): Refunds
```

Devolve o componente de estornos **propagando o tid e o logger**. Não faz requisição.

### Acessores de tid

```php
public function setTid(string $tid): self
public function getTid(): ?string
```

---

## Refunds

`eRede\Components\Refunds`

### Constante

| Constante | Valor |
|---|---|
| `BASE_URL` | `'/v1/transactions/__tid__/refunds'` |

O `__tid__` é substituído em tempo de chamada. Se o `tid` estiver ausente na hora de montar a
URL, lança `InvalidArgumentException('Transactions id not informed or invalid')`.

> Note a versão: estornos ficam em **`/v1`**, transações em `/v2`.

### Construtor

```php
public function __construct(
    PendingRequest $request,
    ?string $tid = null,
    ?string $refundId = null,
    ?LoggerInterface $logger = null,
)
```

### `create()`

```php
public function create(Classes\Amount $amount, Classes\Url $callback, ?string $tid = null): Responses\Refund
```

`POST /v1/transactions/{tid}/refunds` — solicita o estorno. O valor vai em centavos e a URL
entra no array `urls`. Passar `$tid` **sobrescreve** o tid do componente.

```php
$erede->transactions($tid)->refunds()->create(
    new Amount(149.90),
    new Url('https://minhaloja.com.br/webhooks/erede'),
);
```

O estorno é assíncrono: a resposta costuma vir com `status` `PENDING`, e a confirmação chega
no callback. Use `get()` ou `getByTid()` para acompanhar.

### `get()`

```php
public function get(?string $refundId = null, ?string $tid = null): Responses\Refund
```

`GET /v1/transactions/{tid}/refunds/{refundId}` — consulta um estorno específico. Sem
`$refundId` no argumento, usa o do construtor; se nenhum existir, lança
`InvalidArgumentException('Refund id not informed or invalid')`.

### `getByTid()`

```php
public function getByTid(?string $tid = null): Responses\RefundGet
```

`GET /v1/transactions/{tid}/refunds` — lista os estornos da transação.

```php
$lista = $erede->transactions($tid)->refunds()->getByTid();
$lista->getRefunds()?->getStatus();
```

### Acessores de tid

```php
public function setTid(string $tid): self
public function getTid(): ?string
```

---

## Tipos de retorno

| Chamada | Retorno |
|---|---|
| `Transactions::create()` | [`Responses\Transaction`](responses.md#transaction) |
| `Transactions::get()` | [`Responses\TransactionGet`](responses.md#transactionget) |
| `Transactions::getByReference()` | [`Responses\TransactionGet`](responses.md#transactionget) |
| `Transactions::capture()` | [`Responses\Transaction`](responses.md#transaction) |
| `Refunds::create()` | [`Responses\Refund`](responses.md#refund) |
| `Refunds::get()` | [`Responses\Refund`](responses.md#refund) |
| `Refunds::getByTid()` | [`Responses\RefundGet`](responses.md#refundget) |
