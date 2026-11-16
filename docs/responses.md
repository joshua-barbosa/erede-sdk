# Referência — `eRede\Responses`

Objetos de **saída**: você nunca os constrói, eles chegam prontos dos componentes.

## Como a hidratação funciona

Todo response tem um parâmetro final `?array $fromData` no construtor. É por ele que o SDK
converte o JSON da Rede em objeto:

```php
return new ResponsesTransaction(fromData: $this->retrieveResponse($response));
```

O laço de hidratação trata três casos:

1. **Chaves conhecidas de objeto aninhado** (`links`, `authorization`, `capture`, `refunds`,
   `statusHistory`) — instanciam o DTO correspondente.
2. **Qualquer outra chave** — cai em [`Attribute::set()`](traits.md#attribute), que grava
   direto na propriedade de mesmo nome.
3. **Chave que não existe como propriedade** — `Attribute::set()` a **cria dinamicamente**.
   Campos novos da API não quebram o SDK, mas também não ganham getter; leia com
   `$response->get('campoNovo')`.

## Valores monetários

Todo `amount` em resposta vem em **centavos** (`1050` = R$ 10,50), diferente de
[`Classes\Transaction`](classes.md#transaction), cujo construtor recebe reais.

## Panorama

| Response | Origem | Formato |
|---|---|---|
| [`Transaction`](#transaction) | `create()`, `capture()` | Campos na raiz (+ `qrCodeResponse` em Pix) |
| [`TransactionGet`](#transactionget) | `get()`, `getByReference()` | Aninhado em `authorization` / `capture`, ou em `qrCodeResponse` para Pix pendente |
| [`Refund`](#refund) | `refunds()->create()`, `refunds()->get()` | Campos na raiz + histórico |
| [`RefundGet`](#refundget) | `refunds()->getByTid()` | Aninhado em `refunds` |

> **Assimetria importante:** criar uma transação devolve os dados na raiz
> (`$r->getReturnCode()`), mas consultá-la devolve tudo dentro de `authorization`
> (`$r->getAuthorization()->getReturnCode()`). São classes diferentes justamente por isso —
> não dá para tratar as duas do mesmo jeito.

---

## Transaction

`eRede\Responses\Transaction` — resposta de **criação** e de **captura** de transação.

É o objeto que diz se a cobrança passou. O campo decisivo é `returnCode`: `'00'` significa
aprovada, qualquer outro valor é recusa (a mensagem correspondente está em
[`ReturnResponse`](classes.md#returnresponse)).

Na prática você raramente precisa checar o `returnCode` manualmente — o SDK já lança
`eRedeException` com a mensagem traduzida quando a Rede responde com erro HTTP. O `returnCode`
aqui serve para os casos em que a Rede devolve HTTP 200 com código de negação.

### Propriedades

Diferente dos outros DTOs, aqui as propriedades são **públicas** — herança do código original.
Prefira os getters: o acesso direto continua funcionando, mas não é o contrato estável.

```php
public ?string $reference;          public ?string $tid;
public ?string $nsu;                public ?string $brandTid;
public ?string $authorizationCode;  public ?string $dateTime;
public ?int    $amount;             public ?string $cardBin;
public ?string $last4;              public ?string $returnCode;
public ?string $returnMessage;      public ?array  $links;
public ?string $orderId;            public ?Classes\QrCodeResponse $qrCodeResponse;
```

### O que cada campo significa

| Campo | Significado |
|---|---|
| `reference` | Sua referência de pedido, devolvida como veio |
| `tid` | ID da transação na Rede — **guarde**, é ele que permite capturar, consultar e estornar |
| `nsu` | Número sequencial único do comprovante |
| `brandTid` | ID da transação na bandeira (Visa, Master…) |
| `authorizationCode` | Código de autorização do emissor |
| `dateTime` | Data/hora do processamento |
| `amount` | Valor em **centavos** |
| `cardBin` | 6 primeiros dígitos do cartão |
| `last4` | 4 últimos dígitos |
| `returnCode` | Código de retorno (`'00'` = aprovada) |
| `returnMessage` | Mensagem crua da Rede (em inglês) |
| `orderId` | Código do pedido, quando enviado na criação |
| `qrCodeResponse` | [`Classes\QrCodeResponse`](classes.md#qrcoderesponse) — só em solicitações Pix; `null` nas demais |
| `links` | Array de [`Classes\Link`](classes.md#link) |

### Construtor

```php
public function __construct(
    ?string $reference = null, ?string $tid = null, ?string $nsu = null,
    ?string $brandTid = null, ?string $authorizationCode = null, ?string $dateTime = null,
    ?int $amount = null, ?string $cardBin = null, ?string $last4 = null,
    ?string $returnCode = null, ?string $returnMessage = null, ?array $links = null,
    ?array $fromData = null,
)
```

### Métodos

```php
getReference(): ?string          setReference(?string): self
getTid(): ?string                setTid(?string): self
getNsu(): ?string                setNsu(?string): self
getBrandTid(): ?string           setBrandTid(?string): self
getAuthorizationCode(): ?string  setAuthorizationCode(?string): self
getDateTime(): ?string           setDateTime(?string): self
getAmount(): ?int                setAmount(?int): self
getCardBin(): ?string            setCardBin(?string): self
getLast4(): ?string              setLast4(?string): self
getReturnCode(): ?string         setReturnCode(?string): self
getReturnMessage(): ?string      setReturnMessage(?string): self
getLinks(): ?array               setLinks(?array): self
getOrderId(): ?string            setOrderId(?string): self
getQrCodeResponse(): ?Classes\QrCodeResponse
setQrCodeResponse(?Classes\QrCodeResponse): self

addLink(Classes\Link $link): self   // inicializa o array se ainda for null
```

`orderId` e `qrCodeResponse` não entram no construtor posicional — chegam por `fromData` ou
pelos setters.

Em Pix não há cartão: `nsu`, `authorizationCode`, `cardBin` e `last4` ficam `null`, e o que
interessa está em `getTid()` e `getQrCodeResponse()`.

```php
$response = $erede->transactions()->create($transaction->pix(now()->addMinutes(30)));

$response->getQrCodeResponse()->getQrCodeData();   // copia e cola
$response->getQrCodeResponse()->getQrCodeImage();  // base64
```

### Exemplo

```php
$response = $erede->transactions()->create($transaction);

$pedido->update([
    'gateway_tid' => $response->getTid(),
    'nsu'         => $response->getNsu(),
    'bandeira'    => $response->getCardBin(),
    'final'       => $response->getLast4(),
    'valor_cents' => $response->getAmount(),
]);

if (ReturnResponse::STATUS[$response->getReturnCode()] !== 'approved') {
    // recusa devolvida com HTTP 200
}
```

---

## TransactionGet

`eRede\Responses\TransactionGet` — resposta de **consulta** de transação.

Aqui nada fica na raiz: os dados da autorização vêm em `authorization` e, se a transação já foi
capturada, existe também um bloco `capture`. Esse é o objeto que responde *"em que pé está esta
cobrança?"*.

### Propriedades

```php
public ?Classes\Authorization  $authorization;
public ?Classes\Capture        $capture;
public ?array                  $links;
public ?string                 $requestDateTime;
public ?Classes\QrCodeResponse $qrCodeResponse;
public ?array                  $refunds;         // array<int, Classes\Refund>
```

`capture` é `null` quando a transação foi apenas autorizada (criada com
`Transaction::capture(false)`) e ainda não capturada — é assim que você distingue os dois estados.

**Pix muda o formato.** Enquanto o QR Code está pendente a Rede não manda `authorization`: os
dados vêm em `qrCodeResponse` ([`Classes\QrCodeResponse`](classes.md#qrcoderesponse)). Depois de
pago ou devolvido, some o `qrCodeResponse` e aparecem `authorization`, `capture` e, havendo
devoluções, `refunds` — uma lista de [`Classes\Refund`](classes.md#refund).

`requestDateTime`, `qrCodeResponse` e `refunds` não entram no construtor posicional.

### Construtor

```php
public function __construct(
    ?Classes\Authorization $authorization = null,
    ?Classes\Capture $capture = null,
    ?array $links = null,
    ?array $fromData = null,
)
```

### Métodos

```php
getAuthorization(): ?Classes\Authorization    setAuthorization(?Classes\Authorization): void
getCapture(): ?Classes\Capture                setCapture(?Classes\Capture): void
getLinks(): ?array                            setLinks(?array): void
getRequestDateTime(): ?string                 setRequestDateTime(?string): void
getQrCodeResponse(): ?Classes\QrCodeResponse  setQrCodeResponse(?Classes\QrCodeResponse): void
getRefunds(): ?array                          setRefunds(?array): void
addLink(Classes\Link $link): void
```

> Os setters desta classe retornam `void`, não `self` — não encadeie.

### Exemplo

```php
$consulta = $erede->transactions($tid)->get();

$auth = $consulta->getAuthorization();
$auth->getStatus();        // 'Approved', 'Denied', 'Canceled'…
$auth->getReturnCode();    // '00'
$auth->getAmount();        // centavos
$auth->getInstallments();

if ($consulta->getCapture() === null) {
    // autorizada, aguardando captura
} else {
    $consulta->getCapture()->getDateTime();
}
```

Para Pix:

```php
$consulta = $erede->transactions($tid)->get();

if ($qr = $consulta->getQrCodeResponse()) {
    // pendente: o QR Code ainda pode ser exibido
    $qr->getQrCodeData();
} else {
    $auth = $consulta->getAuthorization();
    $auth->getStatus();       // 'Approved' = pago, 'Canceled' = devolvido por inteiro
    $auth->getEndToEndId();

    foreach ($consulta->getRefunds() ?? [] as $devolucao) {
        $devolucao->getAmount();
    }
}
```

---

## Refund

`eRede\Responses\Refund` — resposta de **criação** e de **consulta individual** de estorno.

Estorno pedido depois do dia da transação é processado em D+1: o `create()` devolve
`returnCode` `360` (solicitação aceita) e a confirmação chega depois, via callback ou nova
consulta. Por isso esta classe tem `statusHistory` — a trilha de transições pela qual o estorno
passou. Devolução **Pix** é síncrona e a resposta traz apenas `refundDateTime`, `returnCode` e
`returnMessage`.

### Propriedades

```php
private ?string $returnCode;      private ?string $returnMessage;
private ?string $refundId;        private ?string $tid;
private ?string $nsu;             private ?string $refundDateTime;
private ?string $cancelId;        private ?int    $amount;
private ?string $status;          private ?array  $statusHistory;
private ?array  $links;           private ?string $txid;
```

| Campo | Significado |
|---|---|
| `txid` | Identificador do Pix gerado pelo Itaú — só em devoluções Pix; apenas getter |
| `refundId` | ID do estorno — use para consultar depois com `refunds()->get($refundId)` |
| `tid` | TID da transação estornada |
| `cancelId` | ID de cancelamento, quando a Rede o fornece |
| `status` | Estado atual: `Done`, `Denied` ou `Processing` |
| `statusHistory` | Array de [`Classes\Status`](classes.md#status), a trilha de transições |
| `amount` | Valor estornado em **centavos** |
| `links` | Array de [`Classes\Link`](classes.md#link) |

### Construtor

```php
public function __construct(
    ?string $returnCode = null, ?string $returnMessage = null, ?string $refundId = null,
    ?string $tid = null, ?string $nsu = null, ?string $refundDateTime = null,
    ?string $cancelId = null, ?int $amount = null, ?string $status = null,
    ?array $statusHistory = null, ?array $links = null, ?array $fromData = null,
)
```

### Métodos

```php
getReturnCode(): ?string        setReturnCode(?string): self
getReturnMessage(): ?string     setReturnMessage(?string): self
getRefundId(): ?string          setRefundId(?string): self
getTid(): ?string               setTid(?string): self
getNsu(): ?string               setNsu(?string): self
getRefundDateTime(): ?string    setRefundDateTime(?string): self
getCancelId(): ?string          setCancelId(?string): self
getAmount(): ?int               setAmount(?int): self
getStatus(): ?string            setStatus(?string): self
getStatusHistory(): ?array      setStatusHistory(?array): self
getLinks(): ?array              setLinks(?array): self
getTxid(): ?string

addStatus(Classes\Status $status): self
addLink(Classes\Link $link): self
```

### Exemplo

```php
$estorno = $erede->transactions($tid)->refunds()->create(
    new Amount(149.90),
    new Url('https://minhaloja.com.br/webhooks/erede'),  // opcional
);

$estorno->getRefundId();    // guarde para acompanhar
$estorno->getReturnCode();  // '360' = solicitação aceita

foreach ($estorno->getStatusHistory() ?? [] as $passo) {
    echo $passo->getStatus().' em '.$passo->getDateTime();
}
```

---

## RefundGet

`eRede\Responses\RefundGet` — resposta da **listagem** de estornos de uma transação
(`refunds()->getByTid()`).

A Rede devolve `refunds` como **lista**. Use `getRefundsList()` para percorrer todos os
estornos. `getRefunds()` existe por compatibilidade com versões anteriores do SDK, que tratavam
a chave como um único objeto: ele devolve apenas o **primeiro** item.

### Propriedades

```php
private ?Classes\Refund $refunds;       // primeiro estorno
private array           $refundsList;   // array<int, Classes\Refund> — todos
private ?array          $links;
```

### Construtor

```php
public function __construct(
    ?Classes\Refund $refunds = null,
    ?array $links = null,
    ?array $fromData = null,
)
```

### Métodos

```php
getRefundsList(): array          // array<int, Classes\Refund>
getRefunds(): ?Classes\Refund    setRefunds(?Classes\Refund): self
getLinks(): ?array               setLinks(?array): self
addLink(Classes\Link $link): self
```

### Comportamento na hidratação

- `refunds` vindo como lista → cada item vira um `Classes\Refund` em `refundsList`, e
  `refunds` aponta para o primeiro.
- `refunds` vindo como objeto único → `refundsList` fica com um item.
- `refunds` vazio ou de outro tipo → `refundsList` fica `[]` e `refunds` vira um
  `Classes\Refund` **vazio**, não `null`. Então `getRefunds()` raramente retorna `null` quando a
  chave existe; prefira `getRefundsList()`.
- `setRefunds()` altera só o primeiro estorno; não mexe em `refundsList`.

### Exemplo

```php
$lista = $erede->transactions($tid)->refunds()->getByTid();

foreach ($lista->getRefundsList() as $refund) {
    $refund->getRefundId();
    $refund->getStatus();  // 'Done', 'Denied' ou 'Processing'
    $refund->getAmount();  // centavos
}
```

> **Corrigido nesta extração:** getter, setter e construtor declaravam `?string` enquanto a
> propriedade é `?Refund`. Como a hidratação atribui um objeto, `getRefunds()` lançava
> `TypeError` em toda chamada — o fluxo estava quebrado de ponta a ponta. Ver
> "Corrigido na extração" no [README](../README.md).
