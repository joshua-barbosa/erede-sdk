<?php

namespace eRede\Traits;

use BackedEnum;
use UnitEnum;

trait ToArray
{
    /**
     * @param  array<string,mixed>  $objectArray
     * @return array<string,mixed>
     */
    private function ObjectArrayToArray(array $objectArray, bool $ignoreNullable = true, bool $toSnakeCase = false): array
    {
        $array = [];

        foreach ($objectArray as $key => $value) {
            if ($toSnakeCase) {
                $key = self::toSnakeCase((string) $key);
            }

            if ($ignoreNullable) {
                if (! (is_null($value) === false && empty($value) === false && $value !== null)) {
                    continue;
                }
            }

            if ($value instanceof BackedEnum) {
                $array[$key] = $value->value;
            } elseif ($value instanceof UnitEnum) {
                // Enum puro não tem ->value; o nome é a única representação.
                $array[$key] = $value->name;
            } elseif (is_object($value)) {
                if (in_array('toArray', get_class_methods($value), true)) {
                    $array[$key] = $value->toArray(ignoreNullable: $ignoreNullable, toSnakeCase: $toSnakeCase);
                } else {
                    $array[$key] = $this->ObjectArrayToArray(get_object_vars($value), $ignoreNullable, $toSnakeCase);
                }
            } elseif (is_array($value)) {
                $array[$key] = $this->ObjectArrayToArray($value, $ignoreNullable, $toSnakeCase);
            } else {
                $array[$key] = $value;
            }
        }

        return $array;
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(bool $ignoreNullable = true, bool $toSnakeCase = false): array
    {
        return $this->ObjectArrayToArray(get_object_vars($this), $ignoreNullable, $toSnakeCase);
    }

    /**
     * Equivalente a Str::snake(), reimplementado para não prender o pacote ao
     * comportamento de uma versão específica do illuminate/support.
     */
    private static function toSnakeCase(string $value): string
    {
        if (ctype_lower($value)) {
            return $value;
        }

        $value = preg_replace('/\s+/u', '', ucwords($value)) ?? $value;

        return strtolower((string) preg_replace('/(.)(?=[A-Z])/u', '$1_', $value));
    }
}
