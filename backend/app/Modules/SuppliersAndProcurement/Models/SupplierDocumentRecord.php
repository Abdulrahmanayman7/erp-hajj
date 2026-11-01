<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use Database\Factories\SupplierDocumentRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Eligibility metadata over an immutable Document (ADR-0010). Document bytes and
 * the Document row are never touched; validity lives on this row.
 */
class SupplierDocumentRecord extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierDocumentRecordFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * Verification state and actors are set only by domain Actions.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'requirement_def_id',
        'document_id',
        'expires_at',
        'supersedes_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'verification_status' => DocumentVerificationStatus::class,
            'expires_at' => 'date:Y-m-d',
            'verified_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplierDocumentRecordFactory
    {
        return SupplierDocumentRecordFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierDocumentRecord $record): void {
            if ($record->getAttribute('verification_status') === null) {
                $record->setAttribute('verification_status', DocumentVerificationStatus::PendingVerification);
            }

            if ($record->getAttribute('verification_status') === DocumentVerificationStatus::Superseded) {
                throw new \LogicException('A supplier document record can only become superseded through the supersede action.');
            }

            // M2: denormalized identity + current slot (0 = the one current row).
            $def = SupplierDocumentRequirementDef::query()->findOrFail((int) $record->getAttribute('requirement_def_id'));
            $record->setAttribute('requirement_key', $def->requirement_key);
            $record->setAttribute('current_slot', 0);
        });

        static::updating(function (SupplierDocumentRecord $record): void {
            foreach (['supplier_id', 'requirement_def_id', 'requirement_key', 'document_id', 'uploaded_by', 'supersedes_id'] as $field) {
                if ($record->isDirty($field)) {
                    throw new \LogicException("Supplier document record {$field} is immutable after creation.");
                }
            }

            // Superseded rows free the current slot by taking their (unique) id.
            if ($record->isDirty('verification_status')) {
                if ((string) ($record->getRawOriginal('verification_status')) === DocumentVerificationStatus::Superseded->value) {
                    throw new \LogicException('A superseded supplier document record is final.');
                }

                $record->setAttribute(
                    'current_slot',
                    $record->verification_status === DocumentVerificationStatus::Superseded ? (int) $record->getKey() : 0,
                );
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function requirementDef(): BelongsTo
    {
        return $this->belongsTo(SupplierDocumentRequirementDef::class, 'requirement_def_id');
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }
}
