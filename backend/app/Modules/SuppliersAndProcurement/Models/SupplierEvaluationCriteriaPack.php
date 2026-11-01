<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\SuppliersAndProcurement\Enums\CriteriaPackStatus;
use BackedEnum;
use Database\Factories\SupplierEvaluationCriteriaPackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned evaluation criteria pack per supplier category (IIC-D1).
 */
class SupplierEvaluationCriteriaPack extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierEvaluationCriteriaPackFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CriteriaPackStatus::class,
            'version_no' => 'integer',
            'draft_slot' => 'integer',
            'published_slot' => 'integer',
            'approved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplierEvaluationCriteriaPackFactory
    {
        return SupplierEvaluationCriteriaPackFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (SupplierEvaluationCriteriaPack $pack): void {
            $status = $pack->getAttribute('status');
            if ($status === null) {
                $status = CriteriaPackStatus::Draft;
                $pack->setAttribute('status', $status);
            }

            if (! $status instanceof CriteriaPackStatus) {
                $status = CriteriaPackStatus::from((string) $status);
            }

            $pack->setAttribute(
                'draft_slot',
                $status === CriteriaPackStatus::Draft ? 0 : (int) $pack->getAttribute('version_no'),
            );
            $pack->setAttribute(
                'published_slot',
                $status === CriteriaPackStatus::Published ? 1 : null,
            );
        });

        static::updating(function (SupplierEvaluationCriteriaPack $pack): void {
            $original = $pack->getOriginal('status');
            if (! $original instanceof BackedEnum) {
                $original = CriteriaPackStatus::tryFrom((string) $original);
            }

            if (in_array($original, [CriteriaPackStatus::Published, CriteriaPackStatus::Archived], true)) {
                $allowed = ['status', 'published_slot', 'draft_slot', 'updated_at'];
                $dirty = array_keys($pack->getDirty());
                $forbidden = array_diff($dirty, $allowed);
                // Allow status transition published → archived only via domain Actions.
                if ($original === CriteriaPackStatus::Published
                    && $pack->status === CriteriaPackStatus::Archived
                    && $forbidden === []) {
                    return;
                }

                if ($forbidden !== [] || $original === CriteriaPackStatus::Archived) {
                    throw new \LogicException('Published/archived evaluation criteria packs are immutable.');
                }
            }

            if ($pack->isDirty('supplier_category_id') || $pack->isDirty('version_no')) {
                throw new \LogicException('Criteria pack category and version are immutable.');
            }
        });

        static::deleting(function (SupplierEvaluationCriteriaPack $pack): void {
            if ($pack->status !== CriteriaPackStatus::Draft) {
                throw new \LogicException('Only draft evaluation criteria packs can be deleted.');
            }
        });
    }

    public function isDraft(): bool
    {
        return $this->status === CriteriaPackStatus::Draft;
    }

    public function isPublished(): bool
    {
        return $this->status === CriteriaPackStatus::Published;
    }

    public function editors(): HasMany
    {
        return $this->hasMany(SupplierEvaluationCriteriaPackEditor::class, 'pack_id');
    }

    public function recordEditor(int $userId): void
    {
        if ($this->editors()->where('user_id', $userId)->exists()) {
            return;
        }

        $this->editors()->create([
            'user_id' => $userId,
            'first_edited_at' => now(),
        ]);
    }

    public function hasContributor(int $userId): bool
    {
        if ((int) $this->prepared_by === $userId) {
            return true;
        }

        if ($this->last_edited_by !== null && (int) $this->last_edited_by === $userId) {
            return true;
        }

        return $this->editors()->where('user_id', $userId)->exists();
    }

    public function criteria(): HasMany
    {
        return $this->hasMany(SupplierEvaluationCriterion::class, 'pack_id')->orderBy('sort_order')->orderBy('id');
    }

    public function activeCriteria(): HasMany
    {
        return $this->criteria()->where('is_active', true);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_pack_id');
    }
}
