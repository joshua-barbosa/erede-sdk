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
| [`Transaction`](#transaction) | `create()`, `capture()` | Campos na raiz |
| [`TransactionGet`](#transactionget) | `get()`, `getByReference()` | Aninhado em `authorization` / `capture` |
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

addLink(Classes\Link $link): self   // inicializa o array se ainda for null
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
public ?Classes\Authorization $authorization;
public ?Classes\Capture       $capture;
public ?array                 $links;
```

`capture` é `null` quando a transação foi apenas autorizada (criada com
`Transaction::capture(false)`) e ainda não capturada — é assim que você distingue os dois estados.

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

---

## Refund

`eRede\Responses\Refund` — resposta de **criação** e de **consulta individual** de estorno.

Estorno na Rede é assíncrono: o `create()` costuma devolver `status` `PENDING`, e a confirmação
chega depois (via callback ou nova consulta). Por isso esta classe tem `statusHistory` — a
trilha de transições pela qual o estorno passou.

### Propriedades

```php
private ?string $returnCode;      private ?string $returnMessage;
private ?string $refundId;        private ?string $tid;
private ?string $nsu;             private ?string $refundDateTime;
private ?string $cancelId;        private ?int    $amount;
private ?string $status;          private ?array  $statusHistory;
private ?array  $links;
```

| Campo | Significado |
|---|---|
| `refundId` | ID do estorno — use para consultar depois com `refunds()->get($refundId)` |
| `tid` | TID da transação estornada |
| `cancelId` | ID de cancelamento, quando a Rede o fornece |
| `status` | Estado atual (`PENDING`, `CONFIRMED`, `FAILED`…) |
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

addStatus(Classes\Status $status): self
addLink(Classes\Link $link): self
```

### Exemplo

```php
$estorno = $erede->transactions($tid)->refunds()->create(
    new Amount(149.90),
    new Url('https://minhaloja.com.br/webhooks/erede'),
);

$estorno->getRefundId();  // guarde para acompanhar
$estorno->getStatus();    // normalmente 'PENDING'

foreach ($estorno->getStatusHistory() ?? [] as $passo) {
    echo $passo->getStatus().' em '.$passo->getDateTime();
}
```

---

## RefundGet

`eRede\Responses\RefundGet` — resposta da **listagem** de estornos de uma transação
(`refunds()->getByTid()`).

O nome sugere uma coleção, mas o campo `refunds` é hidratado como **um único**
[`Classes\Refund`](classes.md#refund) — não um array. É o formato que a Rede devolve neste
endpoint; se precisar de todos os estornos de uma transação, percorra os `links`.

### Propriedades

```php
private ?Classes\Refund $refunds;
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
getRefunds(): ?Classes\Refund    setRefunds(?Classes\Refund): self
getLinks(): ?array               setLinks(?array): self
addLink(Classes\Link $link): self
```

### Comportamento na hidratação

- `refunds` vindo como array → vira um `Classes\Refund` populado.
- `refunds` vindo como qualquer outra coisa → vira um `Classes\Refund` **vazio**, não `null`.
  Então `getRefunds()` raramente retorna `null` quando a chave existe; cheque os campos internos.

### Exemplo

```php
$lista = $erede->transactions($tid)->refunds()->getByTid();

if ($refund = $lista->getRefunds()) {
    $refund->getRefundId();
    $refund->getStatus();
    $refund->getAmount();  // centavos
}
```

> **Corrigido nesta extração:** getter, setter e construtor declaravam `?string` enquanto a
> propriedade é `?Refund`. Como a hidratação atribui um objeto, `getRefunds()` lançava
> `TypeError` em toda chamada — o fluxo estava quebrado de ponta a ponta. Ver
> "Corrigido na extração" no [README](../README.md).
