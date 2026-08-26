<?php

namespace App\Support;

class Money
{
    /**
     * Normaliza entrada monetária para decimal com ponto.
     * "1.234,56" → "1234.56" · "1234.56" → "1234.56" · "" → null
     *
     * Valores com vírgula são tratados como pt-BR (pontos = milhar);
     * sem vírgula, o ponto é separador decimal.
     */
    public static function toDecimal(null|string|float|int $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $s = trim((string) $value);

        if (str_contains($s, ',')) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }

        return $s;
    }
}
