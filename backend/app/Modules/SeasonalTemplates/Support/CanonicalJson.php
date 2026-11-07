<?php

namespace App\Modules\SeasonalTemplates\Support;

/**
 * Deterministic JSON (sorted object keys, lists preserved) for content hashes.
 */
final class CanonicalJson
{
    /**
     * @param  array<mixed>  $value
     */
    public static function encode(array $value): string
    {
        return (string) json_encode(self::normalize($value), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);
    }

    /**
     * @param  array<mixed>  $value
     */
    public static function hash(array $value): string
    {
        return hash('sha256', self::encode($value));
    }

    private static function normalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        $normalized = array_map(self::normalize(...), $value);

        if (! array_is_list($normalized)) {
            ksort($normalized);
        }

        return $normalized;
    }
}
