<?php

namespace App\Modules\Assets\Models;

use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Assets\Enums\AssetCondition;
use App\Modules\Assets\Enums\CustodyStatus;
use App\Modules\Assets\Exceptions\AssetDomainException;
use App\Modules\Assets\Support\CustodyNumberGenerator;
use App\Modules\Documents\Models\Document;
use App\Modules\Employees\Models\Employee;
use Database\Factories\AssetCustodyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class AssetCustody extends Model implements TenantOwned
{
    /** @use HasFactory<AssetCustodyFactory> */
    use HasFactory;

    use UsesTenantScope;

    protected $fillable = [
        'asset_id',
        'employee_id',
        'status',
        'assigned_at',
        'expected_return_at',
        'returned_at',
        'assigned_by',
        'returned_by',
        'condition_at_assignment',
        'condition_at_return',
        'assignment_notes',
        'return_notes',
        'correlation_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CustodyStatus::class,
            'assigned_at' => 'datetime',
            'expected_return_at' => 'datetime',
            'returned_at' => 'datetime',
            'condition_at_assignment' => AssetCondition::class,
            'condition_at_return' => AssetCondition::class,
        ];
    }

    protected static function newFactory(): AssetCustodyFactory
    {
        return AssetCustodyFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (AssetCustody $custody): void {
            $number = $custody->getAttribute('custody_number');
            if ($number === null || $number === '') {
                $custody->setAttribute(
                    'custody_number',
                    app(CustodyNumberGenerator::class)->next(),
                );
            }

            if ($custody->getAttribute('status') === null) {
                $custody->setAttribute('status', CustodyStatus::Active);
            }
        });

        static::updating(function (AssetCustody $custody): void {
            if ($custody->getOriginal('status') === CustodyStatus::Returned->value
                || $custody->getOriginal('status') === CustodyStatus::Returned) {
                throw AssetDomainException::immutable();
            }

            if ($custody->isDirty('custody_number') || $custody->isDirty('asset_id') || $custody->isDirty('employee_id')) {
                throw AssetDomainException::immutable();
            }
        });

        static::deleting(function (): void {
            throw AssetDomainException::immutable();
        });
    }

    public function isOverdue(): bool
    {
        return $this->status === CustodyStatus::Active
            && $this->expected_return_at !== null
            && $this->expected_return_at->isPast();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'linkable');
    }
}
