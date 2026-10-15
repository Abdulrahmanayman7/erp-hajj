<?php

namespace App\Modules\SeasonPilgrims\Readiness;

use App\Modules\SeasonPilgrims\Enums\ReadinessLevel;

final class ReadinessResult
{
    public function __construct(
        public readonly ReadinessLevel $level,
        public readonly string $code,
        public readonly string $message,
        public readonly ?int $count = null,
    ) {}

    public static function ok(string $code, string $message): self
    {
        return new self(ReadinessLevel::Ok, $code, $message);
    }

    public static function warning(string $code, string $message, ?int $count = null): self
    {
        return new self(ReadinessLevel::Warning, $code, $message, $count);
    }

    public static function blocking(string $code, string $message, ?int $count = null): self
    {
        return new self(ReadinessLevel::Blocking, $code, $message, $count);
    }

    /**
     * @return array{level: string, code: string, message: string, count: int|null}
     */
    public function toArray(): array
    {
        return [
            'level' => $this->level->value,
            'code' => $this->code,
            'message' => $this->message,
            'count' => $this->count,
        ];
    }
}
