<?php

namespace App\Modules\RisksAndComplaints\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property string $entity_type
 * @property int $entity_id
 * @property string $event_kind
 * @property Carbon $created_at
 */
class OperationalNotificationOccurrence extends Model implements TenantOwned
{
    use UsesTenantScope;

    public $timestamps = false;

    protected $fillable = [
        'entity_type',
        'entity_id',
        'event_kind',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (OperationalNotificationOccurrence $row): void {
            if ($row->getAttribute('created_at') === null) {
                $row->setAttribute('created_at', now());
            }
        });
    }
}
