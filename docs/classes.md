# Referência — `eRede\Classes`

Objetos de **entrada**: você os monta e passa para os componentes. Todos usam os traits
[`ToArray`](traits.md#toarray) e [`Attribute`](traits.md#attribute), então todos expõem
`toArray()`, `set()`, `setMany()`, `get()` e `getMany()` além do que está listado aqui.

Os que aparecem aninhados em respostas (`Authorization`, `Capture`, `Link`, `Status`, `Refund`)
aceitam `fromData` no construtor — é por ele que o SDK hidrata o JSON da Rede.

| Classe | Papel |
|---|---|
| [`Amount`](#amount) | Valor monetário com conversão para centavos |
| [`Transaction`](#transaction) | Payload de criação de transação |
| [`Url`](#url) | URL de callback / 3DS |
| [`Authorization`](#authorization) | Bloco de autorização de uma consulta |
| [`Capture`](#capture) | Bloco de captura de uma consulta |
| [`Refund`](#refund) | Estorno aninhado em `RefundGet` |
| [`Status`](#status) | Item do histórico de status de um estorno |
| [`Link`](#link) | Link HATEOAS devolvido pela Rede |
| [`ReturnResponse`](#returnresponse) | Tabela de códigos de retorno |

---

## Amount

`eRede\Classes\Amount` — valor em reais, convertido para centavos na hora de enviar.

```php
public function __construct(float $amount)
public function setAmount(float $amount): self
public function getAmount(): float          // valor em reais, como informado
public function getConvertedAmount(): int   // valor em centavos, como a Rede espera
```

```php
$amount = new Amount(149.90);
$amount->getAmount();           // 149.9
$amount->getConvertedAmount();  // 14990
```

> A conversão usa `ceil($amount * 100)` — arredonda **para cima**. Ver "Pendências conhecidas" no [README](../README.md).

---

## Transaction

`eRede\Classes\Transaction` — payload enviado em `transactions()->create()`.

### Constantes

| Constante | Valor |
|---|---|
| `CREDIT` | `'credit'` |
| `DEBIT` | `'debit'` |
| `ORIGIN_EREDE` | `1` |
| `ORIGIN_VISA_CHECKOUT` | `4` |
| `ORIGIN_MASTERPASS` | `6` |

### Construtor

```php
public function __construct(?float $amount = null, ?string $reference = null)
```

`$amount` vem em **reais** e é convertido para centavos internamente.
`$reference` é o seu identificador do pedido.

### Atalhos de cartão

```php
public function creditCard(string $cardNumber, string $cardCvv, string|int $expirationMonth, string|int $expirationYear, string $holderName): self
public function debitCard(string $cardNumber, string $cardCvv, string|int $expirationMonth, string|int $expirationYear, string $holderName): self
public function setCard(string $cardNumber, string $securityCode, string|int $expirationMonth, string|int $expirationYear, string $cardHolderName, string $kind): self
```

`creditCard()` define `kind = CREDIT`. `debitCard()` define `kind = DEBIT` **e força `capture(true)`** —
transações de débito são sempre capturadas.

### Captura

```php
public function capture(bool $capture = true): self
```

`capture(false)` autoriza sem capturar (captura posterior via [`Transactions::capture()`](components.md#capture)).
Lança `InvalidArgumentException` se chamado com `false` quando `kind` já é `DEBIT`.

### Acessores

```php
public function getKind(): ?string                   public function setKind(string $kind): self
public function getReference(): ?string              public function setReference(string $reference): self
public function getAmount(): ?int                    public function setAmount(float $amount): self
public function getInstallments(): ?int              public function setInstallments(int $installments): self
public function getCardHolderName(): ?string         public function setCardHolderName(string $cardHolderName): self
public function getCardNumber(): ?string             public function setCardNumber(string $cardNumber): self
public function getExpirationMonth(): ?int           public function setExpirationMonth(int $expirationMonth): self
public function getExpirationYear(): ?int            public function setExpirationYear(int $expirationYear): self
public function getSecurityCode(): ?string           public function setSecurityCode(string $securityCode): self
public function getSoftDescriptor(): ?string         public function setSoftDescriptor(string $softDescriptor): self
public function getSubscription(): ?bool             public function setSubscription(bool $subscription): self
public function getOrigin(): ?int                    public function setOrigin(int $origin): self
public function getDistributorAffiliation(): ?int    public function setDistributorAffiliation(int $distributorAffiliation): self
public function getBrandTid(): ?string               public function setBrandTid(string $brandTid): self
```

> **Atenção à assimetria:** `setAmount()` recebe **reais** e `getAmount()` devolve **centavos**.
> `setAmount(10.50)` seguido de `getAmount()` retorna `1050`.

### Predicados

```php
public function isCredit(): bool
public function isDebit(): bool
public function isVisaCheckout(): bool
public function isMasterpass(): bool
public function isErede(): bool
public function isSubscription(): bool
```

### Exemplo

```php
$transaction = (new Transaction(amount: 149.90, reference: 'pedido-1234'))
    ->creditCard('5448280000000007', '123', 12, 2030, 'JOAO DA SILVA')
    ->setInstallments(3)
    ->setSoftDescriptor('MINHA LOJA');
```

---

## Url

`eRede\Classes\Url` — URL de retorno, usada em estornos e em 3DS.

### Constantes

| Constante | Valor |
|---|---|
| `CALLBACK` | `'callback'` |
| `THREE_D_SECURE_FAILURE` | `'threeDSecureFailure'` |
| `THREE_D_SECURE_SUCCESS` | `'threeDSecureSuccess'` |

```php
public function __construct(string $url, string $kind = self::CALLBACK)
public function getUrl(): string     public function setUrl(string $url): self
public function getKind(): string    public function setKind(string $kind): self
```

```php
new Url('https://minhaloja.com.br/webhooks/erede');
new Url('https://minhaloja.com.br/3ds/ok', Url::THREE_D_SECURE_SUCCESS);
```

---

## Authorization

`eRede\Classes\Authorization` — bloco `authorization` de
[`Responses\TransactionGet`](responses.md#transactionget). Hidratado via `fromData`.

É o objeto mais rico do SDK: quando você **consulta** uma transação, praticamente tudo que
interessa está aqui, não na raiz da resposta. Ele responde "esta cobrança foi aprovada, por
quanto, em quantas parcelas e com qual cartão".

Os campos mais usados na prática:

| Campo | Para que serve |
|---|---|
| `status` | Estado textual: `'Approved'`, `'Denied'`, `'Canceled'`… |
| `returnCode` | Código de retorno (`'00'` = aprovada); traduza com [`ReturnResponse`](#returnresponse) |
| `tid` | TID da transação, o mesmo devolvido na criação |
| `amount` | Valor em **centavos** |
| `installments` | Número de parcelas |
| `cardBin` / `last4` | 6 primeiros e 4 últimos dígitos do cartão |
| `kind` | `'credit'` ou `'debit'` |
| `authorizationCode` | Código de autorização do emissor |
| `nsu` | Número sequencial único do comprovante |
| `affiliation` | Código de afiliação (PV) usado na transação |
| `origin` | Origem da transação (ver constantes em [`Transaction`](#transaction)) |
| `softDescriptor` | Texto exibido na fatura do portador |
| `subscription` | Se é transação recorrente — leia com `isSubscription()` |

```php
public function __construct(
    ?string $dateTime = null, ?string $returnCode = null, ?string $returnMessage = null,
    ?int $affiliation = null, ?string $status = null, ?string $reference = null,
    ?string $tid = null, ?string $nsu = null, ?string $authorizationCode = null,
    ?string $kind = null, ?int $amount = null, ?int $installments = null,
    ?string $cardHolderName = null, ?string $cardBin = null, ?string $last4 = null,
    ?string $softDescriptor = null, ?int $origin = null, ?bool $subscription = null,
    ?array $fromData = [],
)
```

Getters e setters para cada campo acima:

```php
getDateTime(): ?string            setDateTime(?string): self
getReturnCode(): ?string          setReturnCode(?string): self
getReturnMessage(): ?string       setReturnMessage(?string): self
getAffiliation(): ?int            setAffiliation(?int): self
getStatus(): ?string              setStatus(?string): self
getReference(): ?string           setReference(?string): self
getTid(): ?string                 setTid(?string): self
getNsu(): ?string                 setNsu(?string): self
getAuthorizationCode(): ?string   setAuthorizationCode(?string): self
getKind(): ?string                setKind(?string): self
getAmount(): ?int                 setAmount(?int): self
getInstallments(): ?int           setInstallments(?int): self
getCardHolderName(): ?string      setCardHolderName(?string): self
getCardBin(): ?string             setCardBin(?string): self
getLast4(): ?string               setLast4(?string): self
getSoftDescriptor(): ?string      setSoftDescriptor(?string): self
getOrigin(): ?int                 setOrigin(?int): self
isSubscription(): ?bool           setSubscription(?bool): self
```

> O predicado é `isSubscription()`, não `getSubscription()` — divergência em relação a
> [`Transaction`](#transaction), que usa `getSubscription()`. `amount` aqui já vem em **centavos**.

### Exemplo

```php
$auth = $erede->transactions($tid)->get()->getAuthorization();

if ($auth->getStatus() === 'Approved') {
    $auth->getAmount();        // 14990
    $auth->getInstallments();  // 3
    $auth->getLast4();         // '0007'
}
```

---

## Capture

`eRede\Classes\Capture` — bloco `capture` de [`Responses\TransactionGet`](responses.md#transactionget).

Só existe quando a transação **já foi capturada**. É por isso que ele funciona como sinalizador
de estado: numa consulta, `getCapture() === null` significa "autorizada, aguardando captura".

Guarda apenas os dados do momento da captura — que pode ter ocorrido bem depois da autorização,
com valor menor (captura parcial).

| Campo | Significado |
|---|---|
| `dateTime` | Quando a captura ocorreu |
| `nsu` | NSU do comprovante de captura (diferente do da autorização) |
| `amount` | Valor **efetivamente capturado**, em centavos — pode ser menor que o autorizado |
| `brandTid` | ID da captura na bandeira |

```php
public function __construct(?string $dateTime = null, ?string $nsu = null, ?int $amount = null, ?string $brandTid = null, ?array $fromData = [])

getDateTime(): ?string    setDateTime(?string): self
getNsu(): ?string         setNsu(?string): self
getAmount(): ?int         setAmount(?int): self
getBrandTid(): ?string    setBrandTid(?string): self
```

```php
$consulta = $erede->transactions($tid)->get();

if ($capture = $consulta->getCapture()) {
    $capturado  = $capture->getAmount();
    $autorizado = $consulta->getAuthorization()->getAmount();

    if ($capturado < $autorizado) {
        // captura parcial
    }
}
```

---

## Refund

`eRede\Classes\Refund` — estorno aninhado em [`Responses\RefundGet`](responses.md#refundget).

> **Não confundir com [`Responses\Refund`](responses.md#refund).** São classes distintas com o
> mesmo nome curto: esta é o bloco resumido que aparece dentro da *listagem* de estornos; a
> outra é a resposta completa de criar/consultar um estorno e traz também `returnCode`, `nsu`,
> `statusHistory` e `links`. Ao importar as duas no mesmo arquivo, use alias.

| Campo | Significado |
|---|---|
| `refundId` | ID do estorno na Rede |
| `refundDateTime` | Quando o estorno foi processado |
| `cancelId` | ID de cancelamento, quando a Rede o fornece |
| `status` | `PENDING`, `CONFIRMED`, `FAILED`… |
| `amount` | Valor estornado em **centavos** |

```php
public function __construct(?string $refundId = null, ?string $refundDateTime = null, ?string $cancelId = null, ?string $status = null, ?int $amount = null, ?array $fromData = null)

getRefundId(): ?string          setRefundId(?string): self
getRefundDateTime(): ?string    setRefundDateTime(?string): self
getCancelId(): ?string          setCancelId(?string): self
getStatus(): ?string            setStatus(?string): self
getAmount(): ?int               setAmount(?int): self
```

---

## Status

`eRede\Classes\Status` — um passo do `statusHistory` de [`Responses\Refund`](responses.md#refund).

Estorno na Rede é assíncrono e passa por estados sucessivos. Cada `Status` é uma linha dessa
trilha: o estado e o instante em que ele foi atingido. Serve para auditoria — reconstruir
quando o estorno saiu de `PENDING` para `CONFIRMED`.

```php
public function __construct(?string $status = null, ?string $dateTime = null, ?array $fromData = null)

getStatus(): ?string      setStatus(?string): self
getDateTime(): ?string    setDateTime(?string): self
```

```php
foreach ($estorno->getStatusHistory() ?? [] as $passo) {
    logger()->info("Estorno {$passo->getStatus()} em {$passo->getDateTime()}");
}
```

---

## Link

`eRede\Classes\Link` — link HATEOAS, presente em quase toda resposta.

A Rede devolve, junto dos dados, os endereços das operações possíveis a partir daquele recurso
(consultar a transação, criar um estorno…). O SDK hidrata cada um como um `Link`; você raramente
precisa deles, porque os componentes já montam as URLs, mas ficam disponíveis para navegação
dinâmica ou depuração.

| Campo | Significado |
|---|---|
| `method` | Verbo HTTP da operação (`GET`, `POST`…) |
| `rel` | Relação — o que aquele link representa (`self`, `refund`, `transaction`…) |
| `href` | URL absoluta |

```php
public function __construct(?string $method = null, ?string $rel = null, ?string $href = null, ?array $fromData = [])

getMethod(): ?string    setMethod(?string): self
getRel(): ?string       setRel(?string): self
getHref(): ?string      setHref(?string): self
```

```php
foreach ($response->getLinks() ?? [] as $link) {
    if ($link->getRel() === 'refund') {
        $link->getHref();
    }
}
```

---

## ReturnResponse

`eRede\Classes\ReturnResponse` — tabela estática com os 278 códigos de retorno da Rede.
É ela que traduz o código em mensagem nas exceções do SDK.

### Constantes

| Constante | Conteúdo |
|---|---|
| `CODES` | Lista dos 278 códigos válidos (strings, ex.: `'00'`, `'51'`) |
| `MESSAGES` | Mapa código → mensagem em português |
| `STATUS` | Mapa código → `'approved'` ou `'failed'` |

### Métodos

```php
public static function existsReturnCode(?string $code = null): bool
public static function getReturnMessage(?string $code = null): string
```

`getReturnMessage()` **nunca** devolve `null`: para código desconhecido ou `null` retorna
`'Erro no processamento (Erro não mapeado). Tente novamente'`.

```php
ReturnResponse::getReturnMessage('00');   // 'Sucesso'
ReturnResponse::getReturnMessage('3');    // 'Parâmetro obrigatório não está presente'
ReturnResponse::existsReturnCode('999');  // false

// Aprovada?
ReturnResponse::STATUS[$response->getReturnCode()] === 'approved';
```

> Os códigos são **strings**, e sem zero à esquerda exceto `'00'` — é `'1'`, não `'01'`.
