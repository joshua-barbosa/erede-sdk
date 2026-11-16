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
| [`Refunds`](#refunds) | `/v2/transactions/{tid}/refunds` | `Transactions::refunds()` |

As URLs base vêm de `eRede::URL` e seguem a documentação da Rede:

| Ambiente | API | OAuth 2.0 |
|---|---|---|
| Sandbox | `https://sandbox-erede.useredecloud.com.br` | `https://rl7-sandbox-api.useredecloud.com.br/oauth2/token` |
| Produção | `https://api.userede.com.br/erede` | `https://api.userede.com.br/redelabs/oauth2/token` |

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

`POST /v2/transactions` — cria (autoriza e, por padrão, captura) uma transação. É também o
endpoint que solicita um QR Code [Pix](#pix), quando a transação foi montada com
`Transaction::pix()`.

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
| `BASE_URL` | `'/v2/transactions/__tid__/refunds'` |

O `__tid__` é substituído em tempo de chamada. Se o `tid` estiver ausente na hora de montar a
URL, lança `InvalidArgumentException('Transactions id not informed or invalid')`.

> Estornos e transações ficam ambos em **`/v2`**, como na documentação da Rede.

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
public function create(Classes\Amount $amount, ?Classes\Url $callback = null, ?string $tid = null): Responses\Refund
```

`POST /v2/transactions/{tid}/refunds` — solicita o estorno. O valor vai em centavos. O
`$callback` é **opcional**: quando informado entra no array `urls`; quando omitido, o corpo leva
só o `amount` e vale a URL cadastrada no portal da Rede. Passar `$tid` **sobrescreve** o tid do
componente.

```php
$erede->transactions($tid)->refunds()->create(
    new Amount(149.90),
    new Url('https://minhaloja.com.br/webhooks/erede'),
);
```

Pedido no mesmo dia da transação é processado na hora; depois disso, em D+1 — a resposta traz
`returnCode` `360` (solicitação aceita, **não** estorno efetivado) e a confirmação chega no
callback. Use `get()` ou `getByTid()` para acompanhar: o `status` é `Done`, `Denied` ou
`Processing`.

Devolução **Pix** é síncrona e aceita apenas o valor — ver [Pix](#pix).

### `get()`

```php
public function get(?string $refundId = null, ?string $tid = null): Responses\Refund
```

`GET /v2/transactions/{tid}/refunds/{refundId}` — consulta um estorno específico. Sem
`$refundId` no argumento, usa o do construtor; se nenhum existir, lança
`InvalidArgumentException('Refund id not informed or invalid')`.

### `getByTid()`

```php
public function getByTid(?string $tid = null): Responses\RefundGet
```

`GET /v2/transactions/{tid}/refunds` — lista os estornos da transação.

```php
$lista = $erede->transactions($tid)->refunds()->getByTid();

foreach ($lista->getRefundsList() as $refund) {
    $refund->getRefundId();
    $refund->getStatus();
}
```

### Acessores de tid

```php
public function setTid(string $tid): self
public function getTid(): ?string
```

---

## Pix

Pix não tem componente próprio: usa os mesmos endpoints de transação e cancelamento.

| Operação | Chamada | Endpoint |
|---|---|---|
| Solicitar QR Code | `transactions()->create($transaction->pix(...))` | `POST /v2/transactions` |
| Consultar por TID | `transactions($tid)->get()` | `GET /v2/transactions/{tid}` |
| Consultar por reference | `transactions()->getByReference($ref)` | `GET /v2/transactions?reference={reference}` |
| Devolver | `transactions($tid)->refunds()->create(new Amount(...))` | `POST /v2/transactions/{tid}/refunds` |
| Devoluções da transação | `transactions($tid)->refunds()->getByTid()` | `GET /v2/transactions/{tid}/refunds` |
| Devolução específica | `transactions($tid)->refunds()->get($refundId)` | `GET /v2/transactions/{tid}/refunds/{refundId}` |

> Método disponível apenas para **correntistas Itaú**, com a chave Pix habilitada no portal da
> Rede (*Para vender > PIX*).

### Solicitar o QR Code

```php
$transaction = (new Transaction(amount: 39.00, reference: 'pedido-1234'))
    ->pix('2030-01-15T23:59:00');

$response = $erede->transactions()->create($transaction);
```

Corpo enviado:

```json
{
    "kind": "Pix",
    "reference": "pedido-1234",
    "amount": 3900,
    "qrCode": { "dateTimeExpiration": "2030-01-15T23:59:00" }
}
```

A resposta é um [`Responses\Transaction`](responses.md#transaction) com o bloco
[`qrCodeResponse`](classes.md#qrcoderesponse): `qrCodeData` (copia e cola) e `qrCodeImage`
(base64).

### Consultar

A Rede devolve dois formatos, e o [`Responses\TransactionGet`](responses.md#transactionget)
reflete os dois:

| Situação | O que vem preenchido |
|---|---|
| QR Code **pendente** | `getQrCodeResponse()` — status `Pending`, com `qrCodeData` e `qrCodeImage`; `getAuthorization()` é `null` |
| QR Code **pago** ou **devolvido** | `getAuthorization()` (status `Approved` / `Canceled`, `txid`, `endToEndId`), `getCapture()` e `getRefunds()`; `getQrCodeResponse()` é `null` |

Transação parcialmente devolvida continua `Approved` até o saldo inteiro ser devolvido.
QR Code expirado lança `eRedeException` com `returnCode()` `'3036'`.

### Devolver

```php
$refund = $erede->transactions($tid)->refunds()->create(new Amount(10.00));
$refund->getReturnCode();      // '360' = devolução efetuada
```

Total ou parcial, em até 90 dias. É **síncrona**: não há callback nem notificação para
devoluções pedidas pela API — o resultado é o `returnCode` da própria resposta.

### Webhook

O pagamento e as devoluções feitas por canais Itaú (bankline, por exemplo) são avisados por
`POST` na URL que você cadastra **por CNPJ** junto à central de atendimento da Rede — não é
configurável pela API em produção. Sem URL cadastrada, nenhum evento é enviado.

| Evento | Significado |
|---|---|
| `PV.UPDATE_TRANSACTION_PIX` | QR Code pago |
| `PV.REFUND_PIX` | Devolvido (parcial ou totalmente) via canais Itaú |

```json
{
    "id": "f526fd25-da12-4874-a24d-c926186301e9",
    "merchantId": "90104480",
    "events": ["PV.UPDATE_TRANSACTION_PIX"],
    "data": {
        "txid": "RERO8044890090104480CV94HGTH46B6BD2",
        "id": "40402508050758050105",
        "endToEndId": "E0000000020241219172445877200001"
    }
}
```

`data.id` é o TID. O SDK não processa o webhook — receba-o numa rota sua e, se quiser os
detalhes, consulte com `transactions($tid)->get()`. A Rede recomenda aguardar ao menos 10
minutos após a notificação antes de consultar.

### Sandbox

No sandbox o pagamento é simulado sozinho: a notificação `PV.UPDATE_TRANSACTION_PIX` sai 2
minutos depois da solicitação do QR Code (um QR Code de R$ 50,00 dispara também `PV.REFUND_PIX`).
A URL de teste é registrada com `POST /v2/transactions/notification-url`, endpoint exclusivo do
sandbox que o SDK não encapsula. Para simular erros, envie o código desejado como valor em
centavos (`amount = 3090` devolve o código `3090`, por exemplo).

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
