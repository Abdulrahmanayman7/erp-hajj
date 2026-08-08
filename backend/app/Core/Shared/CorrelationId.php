<?php

namespace App\Core\Shared;

/**
 * Request-scoped correlation ID (docs/06-security/AUDIT_TRAIL.md).
 * Not a secret; never used for authorization.
 */
final class CorrelationId
{
    private ?string $value = null;

    public function get(): ?string
    {
        return $this->value;
    }

    public function set(string $value): void
    {
        $this->value = $value;
    }

    public function require(): string
    {
        return $this->value ?? throw new \LogicException('Correlation ID has not been assigned for this request.');
    }
}
