<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\EligibilityConfigStatus;
use BackedEnum;
use Database\Factories\SupplierEligibilityRequirementSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned eligibility configuration (C1: approved versions are immutable).
 */
class SupplierEligibilityRequirementSet extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierEligibilityRequirementSetFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * Versioning, authorship and approval fields are set only by domain Actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'config_key',
        'name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EligibilityConfigStatus::class,
            'version_no' => 'integer',
            'draft_slot' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplierEligibilityRequirementSetFactory
    {
        return SupplierEligibilityRequirementSetFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (SupplierEligibilityRequirementSet $set): void {
            $status = $set->getAttribute('status');
            if ($status === null) {
                $status = EligibilityConfigStatus::Draft;
                $set->setAttribute('status', $status);
            }

            $set->setAttribute(
                'draft_slot',
                $status === EligibilityConfigStatus::Draft ? 0 : (int) $set->getAttribute('version_no'),
            );
        });

        static::updating(function (SupplierEligibilityRequirementSet $set): void {
            $original = $set->getOriginal('status');
            if (! $original instanceof BackedEnum) {
                $original = EligibilityConfigStatus::tryFrom((string) $original);
            }

            if ($original === EligibilityConfigStatus::Approved) {
                throw new \LogicException('Approved eligibility configuration versions are immutable.');
            }

            if ($set->isDirty('config_key') || $set->isDirty('version_no')) {
                throw new \LogicException('Eligibility configuration key and version are immutable.');
            }
        });

        static::deleting(function (SupplierEligibilityRequirementSet $set): void {
            if ($set->status === EligibilityConfigStatus::Approved) {
                throw new \LogicException('Approved eligibility configuration versions cannot be deleted.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === EligibilityConfigStatus::Draft;
    }

    public function isApproved(): bool
    {
        return $this->status === EligibilityConfigStatus::Approved;
    }

    public function editors(): HasMany
    {
        return $this->hasMany(SupplierEligibilityRequirementSetEditor::class, 'requirement_set_id');
    }

    /**
     * Records the user as a contributor of this version (idempotent). Called by domain
     * Actions while holding the row lock, so the unique index is only a backstop.
     */
    public function recordEditor(int $userId): void
    {
        $exists = $this->editors()->where('user_id', $userId)->exists();
        if ($exists) {
            return;
        }

        $this->editors()->create([
            'user_id' => $userId,
            'first_edited_at' => now(),
        ]);
    }

    /**
     * C3: author, last editor and every recorded editor are contributors.
     */
    public function hasContributor(int $userId): bool
    {
        return (int) $this->authored_by === $userId
            || (int) $this->last_edited_by === $userId
            || $this->editors()->where('user_id', $userId)->exists();
    }

    public function requirementDefs(): HasMany
    {
        return $this->hasMany(SupplierDocumentRequirementDef::class, 'requirement_set_id')->orderBy('id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authored_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
