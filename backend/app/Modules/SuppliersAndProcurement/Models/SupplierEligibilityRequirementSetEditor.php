<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of every user who authored or edited a requirement-set version
 * (C3 separation of duties: none of them may approve that version).
 */
class SupplierEligibilityRequirementSetEditor extends Model implements TenantOwned
{
    use UsesTenantScope;

    /** Shortened: the default name would exceed MySQL's 64-char FK identifier limit. */
    protected $table = 'supplier_eligibility_set_editors';

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requirement_set_id',
        'user_id',
        'first_edited_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_edited_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \LogicException('Eligibility requirement set editor history is append-only.');
        });

        static::deleting(function (): void {
            throw new \LogicException('Eligibility requirement set editor history is append-only.');
        });
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(SupplierEligibilityRequirementSet::class, 'requirement_set_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
