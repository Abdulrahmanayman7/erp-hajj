<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Modules\SuppliersAndProcurement\Enums\SupplierType;
use Database\Factories\SupplierDocumentRequirementDefFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Requirement row belonging to exactly one versioned set. Rows of an approved
 * set are immutable (C1): insert/update/delete are rejected at model level.
 */
class SupplierDocumentRequirementDef extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierDocumentRequirementDefFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'requirement_set_id',
        'requirement_key',
        'label_ar',
        'description',
        'supplier_type',
        'country_code',
        'supplier_category_id',
        'is_mandatory',
        'default_validity_days',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'supplier_type' => SupplierType::class,
            'is_mandatory' => 'boolean',
            'default_validity_days' => 'integer',
        ];
    }

    protected static function newFactory(): SupplierDocumentRequirementDefFactory
    {
        return SupplierDocumentRequirementDefFactory::new();
    }

    protected static function booted(): void
    {
        static::saving(function (SupplierDocumentRequirementDef $def): void {
            $country = $def->getAttribute('country_code');
            $country = is_string($country) && trim($country) !== '' ? strtoupper(trim($country)) : null;
            $def->setAttribute('country_code', $country);

            // MySQL/MariaDB: the *_norm columns are STORED generated columns (M3) and must
            // never be written. SQLite (tests) keeps physical columns maintained here.
            if (! in_array($def->getConnection()->getDriverName(), ['mysql', 'mariadb'], true)) {
                $def->setAttribute('country_code_norm', $country ?? '');

                $categoryId = $def->getAttribute('supplier_category_id');
                $def->setAttribute('supplier_category_id_norm', $categoryId === null ? 0 : (int) $categoryId);
            }

            if ($def->exists) {
                $def->assertSetIsDraft((int) $def->getOriginal('requirement_set_id'));
            }
            $def->assertSetIsDraft((int) $def->getAttribute('requirement_set_id'));
        });

        static::deleting(function (SupplierDocumentRequirementDef $def): void {
            $def->assertSetIsDraft((int) $def->getAttribute('requirement_set_id'));
        });
    }

    private function assertSetIsDraft(int $setId): void
    {
        $set = SupplierEligibilityRequirementSet::query()->whereKey($setId)->first();
        if ($set !== null && $set->isApproved()) {
            throw new \LogicException('Requirement definitions of an approved version are immutable.');
        }
    }

    public function requirementSet(): BelongsTo
    {
        return $this->belongsTo(SupplierEligibilityRequirementSet::class, 'requirement_set_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(SupplierCategory::class, 'supplier_category_id');
    }
}
