<?php

namespace eRede\Traits;

trait Attribute
{
    public function set(string $key, mixed $value): void
    {
        $this->{$key} = $value;
    }

    public function setMany(mixed ...$vars): void
    {
        if ($vars && count(value: $vars) > 0) {
            foreach ($vars as $key => $value) {
                if (is_numeric(value: $key)) {
                    continue;
                } else {
                    $this->{$key} = $value;
                }
            }
        }
    }

    public function get(string $key): mixed
    {
        return $this->{$key} ?? null;
    }

    public function getMany(mixed ...$vars): array
    {
        $variables = get_object_vars($this);
        $return = [];

        if ($vars && count(value: $vars) > 0) {
            foreach ($vars as $value) {
                if (isset($variables[$value])) {
                    $return[$value] = $variables[$value];
                }
            }
        }

        return $return;
    }
}
