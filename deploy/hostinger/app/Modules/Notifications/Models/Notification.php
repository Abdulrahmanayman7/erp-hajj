<?php

namespace App\Modules\Notifications\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationSeverity;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Exceptions\NotificationDomainException;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Tenant-owned in-app notification (recipient = User).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $recipient_user_id
 * @property NotificationType $type
 * @property string $title
 * @property string|null $body
 * @property NotificationSeverity $severity
 * @property string|null $entity_type
 * @property int|null $entity_id
 * @property string|null $dedupe_key
 * @property Carbon|null $read_at
 * @property string|null $correlation_id
 */
class Notification extends Model implements TenantOwned
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * Mass assignment hardened — dispatcher uses forceFill.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => NotificationType::class,
            'severity' => NotificationSeverity::class,
            'read_at' => 'datetime',
            'entity_id' => 'integer',
        ];
    }

    protected static function newFactory(): NotificationFactory
    {
        return NotificationFactory::new();
    }

    protected static function booted(): void
    {
        static::updating(function (Notification $notification): void {
            $dirty = array_keys($notification->getDirty());
            $allowed = ['read_at', 'updated_at'];

            if (array_diff($dirty, $allowed) !== []) {
                throw NotificationDomainException::immutable();
            }
        });

        static::deleting(function (): void {
            throw NotificationDomainException::immutable();
        });
    }

    public function isRead(): bool
    {
        return $this->read_at !== null;
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}
