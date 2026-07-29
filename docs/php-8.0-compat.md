# Concessões feitas para suportar PHP 8.0

O pacote suporta `php: ^8.0` para alcançar projetos legados em Laravel 8 e 9.
Isso custou alguns recursos de PHP 8.1+. Este documento registra exatamente o
que foi cedido e como reverter quando o piso subir para 8.1.

## 1. `readonly` removido de 8 propriedades

`readonly` é PHP 8.1. Em 8.0 gera `Parse error`, então as declarações abaixo
perderam a palavra-chave. **A imutabilidade continua valendo por convenção** —
não há setters e `Config::with()` sempre devolve nova instância — mas deixou de
ser garantida pelo runtime.

### Locais exatos

| Arquivo | Propriedade |
|---|---|
| `src/Support/Config.php` | `public readonly ?string $pv` |
| `src/Support/Config.php` | `public readonly ?string $token` |
| `src/Support/Config.php` | `public readonly string $env` |
| `src/Support/Config.php` | `public readonly array $http` |
| `src/Support/Config.php` | `public readonly array $cache` |
| `src/Support/Config.php` | `public readonly array $logging` |
| `src/eRede.php` | `private readonly Config $config` |
| `src/eRede.php` | `private readonly ?Container $container` |

Em `Config` são os 6 parâmetros promovidos do construtor, em sequência. Em
`eRede` são as duas primeiras propriedades declaradas na classe.

### Como restaurar

```bash
# 1. Recolocar a palavra-chave
sed -i 's/^        public \(?\?string\|string\|array\) \$/        public readonly \1 $/' src/Support/Config.php
sed -i 's/^    private Config \$config;/    private readonly Config $config;/' src/eRede.php
sed -i 's/^    private ?Container \$container;/    private readonly ?Container $container;/' src/eRede.php

# 2. Subir o piso no composer.json
#    "php": "^8.0"  ->  "php": "^8.1"

# 3. Reverter a nota no docblock de Config (src/Support/Config.php),
#    voltando para "Configuração imutável do SDK."

# 4. Conferir
vendor/bin/phpunit && vendor/bin/pint --test
```

Confira o resultado com `grep -n readonly src/Support/Config.php src/eRede.php` —
devem aparecer exatamente 8 ocorrências.

## 2. Enums dos testes movidos para fixture

`enum` também é PHP 8.1. Os enums usados por `ToArrayBranchesTest` para exercitar
os ramos `BackedEnum` / `UnitEnum` de `Traits\ToArray` saíram do arquivo de teste
para `tests/Fixtures/Enums.php`, carregado sob demanda:

```php
if (PHP_VERSION_ID < 80100) {
    $this->markTestSkipped('Enums exigem PHP 8.1+');
}

require_once __DIR__.'/../Fixtures/Enums.php';
```

Se estivessem declarados no próprio arquivo de teste, o PHP 8.0 daria parse error
ao carregá-lo — antes de qualquer `markTestSkipped()` ter chance de rodar.

Ao subir o piso para 8.1, os enums podem voltar para dentro do arquivo de teste e
a guarda de versão pode sair.

## 3. Metadados de teste sem atributos

Não é concessão de PHP, e sim de PHPUnit: o Laravel 8 traz PHPUnit 9, que ignora
atributos como `#[Test]` e `#[DataProvider]` (são PHPUnit 10+). Os testes usam o
prefixo `test_` no nome do método, que funciona em todas as versões, e os data
providers viraram laços dentro do próprio teste.

Se um dia o piso subir para Laravel 10+ (PHPUnit 10+), dá para voltar aos
atributos — mas o prefixo `test_` não incomoda, então provavelmente não vale o
churn.

## Quando reverter tudo isso

Quando o suporte a Laravel 8 e 9 for removido. Ver a tabela de compatibilidade e
o aviso de descontinuação no [README](../README.md#compatibilidade).
