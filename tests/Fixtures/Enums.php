<?php

/*
 * Enums usados por ToArrayBranchesTest para exercitar os ramos BackedEnum e
 * UnitEnum de eRede\Traits\ToArray.
 *
 * Ficam neste arquivo separado, e não dentro do teste, porque `enum` é PHP 8.1
 * e o pacote suporta 8.0. Declarados no arquivo de teste, o PHP 8.0 daria parse
 * error ao carregá-lo — antes de qualquer markTestSkipped() ter chance de rodar.
 *
 * Carregado sob demanda via require_once, só quando PHP_VERSION_ID >= 80100.
 * Por isso não está no autoload-dev do composer.json.
 */

namespace eRede\Tests\Fixtures;

enum MoedaBacked: string
{
    case BRL = 'BRL';
}

enum BandeiraPura
{
    case VISA;
}
