<?php

namespace App\Modules\Audit\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Hybrid append-only audit row (nullable tenant_id for platform-only events).
 * Does not use UsesTenantScope — tenant reads filter explicitly.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property string $context_type
 * @property string $actor_type
 * @property int|null $actor_user_id
 * @property string|null $actor_label
 * @property string $event_type
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $entity_number
 * @property string|null $entity_label
 * @property string|null $reason
 * @property array<string, mixed>|null $metadata
 * @property array<string, mixed>|null $before_values
 * @property array<string, mixed>|null $after_values
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $correlation_id
 * @property string $source
 * @property Carbon $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'before_values' => 'array',
            'after_values' => 'array',
            'created_at' => 'datetime',
            'entity_id' => 'integer',
            'actor_user_id' => 'integer',
            'tenant_id' => 'integer',
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public static function record(array $attributes): self
    {
        $model = new self;
        $model->forceFill($attributes);
        $model->save();

        return $model;
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            throw new LogicException('Audit logs are append-only and cannot be updated.');
        }

        return parent::save($options);
    }

    public function update(array $attributes = [], array $options = []): bool
    {
        throw new LogicException('Audit logs are append-only and cannot be updated.');
    }

    public function delete(): ?bool
    {
        throw new LogicException('Audit logs are append-only and cannot be deleted.');
    }

    public function forceDelete(): ?bool
    {
        throw new LogicException('Audit logs are append-only and cannot be deleted.');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
