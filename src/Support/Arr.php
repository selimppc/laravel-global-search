<?php

namespace Selimppc\GlobalSearch\Support;

class Arr
{
    public static function only(array $src, array $keys): array
    {
        $out = [];
        foreach ($keys as $k) {
            if (array_key_exists($k, $src)) $out[$k] = $src[$k];
        }
        return $out;
    }
}