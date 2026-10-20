<?php

namespace App\Support;

use Illuminate\Support\Str;
use InvalidArgumentException;
use JsonException;

class ProductVariantSpreadsheet
{
    /** @param list<string> $colors */
    public static function format(array $colors): string
    {
        foreach ($colors as $color) {
            if (str_contains($color, '//') || str_contains($color, "\n") || str_contains($color, "\r") || str_starts_with($color, '[')) {
                return json_encode($colors, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            }
        }

        return implode(' // ', $colors);
    }

    /** @return list<string> */
    public static function parse(mixed $value, int $row): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
            throw new InvalidArgumentException("Fila {$row}: Variantes por color debe contener texto.");
        }
        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }
        if (str_starts_with($text, '[')) {
            try {
                $colors = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                throw new InvalidArgumentException("Fila {$row}: la lista JSON de variantes no es válida.");
            }
            if (! is_array($colors) || ! array_is_list($colors)) {
                throw new InvalidArgumentException("Fila {$row}: las variantes deben ser una lista de colores.");
            }
        } else {
            $colors = preg_split('/\r\n|\r|\n|\/\//', $text) ?: [];
        }
        $result = [];
        foreach ($colors as $color) {
            if (! is_string($color)) {
                throw new InvalidArgumentException("Fila {$row}: cada variante debe ser un texto.");
            }
            $color = trim($color);
            if ($color === '') {
                continue;
            }
            if (Str::length($color) > 255) {
                throw new InvalidArgumentException("Fila {$row}: cada color debe tener como máximo 255 caracteres.");
            }
            $key = Str::lower($color);
            if (! array_key_exists($key, $result)) {
                $result[$key] = $color;
            }
        }

        return array_values($result);
    }
}
