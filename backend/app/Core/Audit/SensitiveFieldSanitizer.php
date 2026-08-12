<?php

namespace App\Core\Audit;

/**
 * Denylist + recursive sanitization for audit metadata / before / after payloads.
 */
final class SensitiveFieldSanitizer
{
    private const MAX_ENCODED_BYTES = 65536;

    private const MAX_STRING_LENGTH = 2000;

    private const MAX_DEPTH = 6;

    /**
     * @var list<string>
     */
    private const DENIED_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'plain_text_token',
        'personal_access_token',
        'api_key',
        'secret',
        'authorization',
        'cookie',
        'cookies',
        'session_id',
        'csrf',
        'csrf_token',
        '_token',
        'storage_path',
        'storage_disk',
        'stored_filename',
        'file_bytes',
        'file_content',
        'content',
        'hash',
        'password_hash',
    ];

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    public function sanitize(?array $payload): ?array
    {
        if ($payload === null || $payload === []) {
            return $payload === [] ? [] : null;
        }

        $clean = $this->sanitizeRecursive($payload, 0);
        $encoded = json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        if (strlen($encoded) > self::MAX_ENCODED_BYTES) {
            $clean = ['_truncated' => true, 'keys' => array_keys($clean)];
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizeRecursive(array $payload, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return ['_truncated_depth' => true];
        }

        $out = [];
        foreach ($payload as $key => $value) {
            $keyStr = is_string($key) ? $key : (string) $key;
            if ($this->isDeniedKey($keyStr)) {
                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $value */
                $out[$keyStr] = $this->sanitizeRecursive($value, $depth + 1);

                continue;
            }

            if (is_string($value)) {
                $out[$keyStr] = mb_substr($value, 0, self::MAX_STRING_LENGTH);

                continue;
            }

            if (is_scalar($value) || $value === null) {
                $out[$keyStr] = $value;
            }
        }

        return $out;
    }

    private function isDeniedKey(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::DENIED_KEYS as $denied) {
            if ($normalized === $denied || str_contains($normalized, $denied)) {
                return true;
            }
        }

        return false;
    }
}
