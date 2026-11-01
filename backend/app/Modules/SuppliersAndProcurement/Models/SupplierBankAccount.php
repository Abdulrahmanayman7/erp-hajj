<?php

namespace App\Modules\SuppliersAndProcurement\Models;

use App\Core\Tenancy\TenantOwned;
use App\Core\Tenancy\UsesTenantScope;
use App\Models\User;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\SupplierBankAccountStatus;
use Database\Factories\SupplierBankAccountFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Versioned supplier bank-account row (S04 II-B WP-01 foundations).
 * Plaintext identifiers are never exposed via serialization; ciphertext columns are hidden.
 */
class SupplierBankAccount extends Model implements TenantOwned
{
    /** @use HasFactory<SupplierBankAccountFactory> */
    use HasFactory;

    use UsesTenantScope;

    /**
     * Ciphertext and fingerprint are intentionally NOT fillable — use storeEncryptedIdentifiers().
     * Status/primary/slot are set by domain Actions (WP-02); fillable for foundation/tests only.
     *
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'account_key',
        'version_no',
        'label',
        'account_holder_name',
        'bank_name',
        'iban_last4',
        'account_last4',
        'currency',
        'status',
        'current_slot',
        'is_primary',
        'supersedes_id',
        'evidence_document_id',
        'created_by',
        'last_edited_by',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'iban_ciphertext',
        'account_ciphertext',
        'iban_fingerprint',
        'primary_flag',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SupplierBankAccountStatus::class,
            'is_primary' => 'boolean',
            'iban_ciphertext' => 'encrypted',
            'account_ciphertext' => 'encrypted',
            'version_no' => 'integer',
            'current_slot' => 'integer',
            'verified_at' => 'datetime',
            'rejected_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function newFactory(): SupplierBankAccountFactory
    {
        return SupplierBankAccountFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (SupplierBankAccount $account): void {
            if ($account->getAttribute('status') === null) {
                $account->setAttribute('status', SupplierBankAccountStatus::Pending);
            }

            if ($account->getAttribute('currency') === null || $account->getAttribute('currency') === '') {
                $account->setAttribute('currency', 'SAR');
            }

            if ($account->getAttribute('current_slot') === null) {
                $account->setAttribute('current_slot', 0);
            }

            if ($account->getAttribute('is_primary') === null) {
                $account->setAttribute('is_primary', false);
            }

            if ($account->getAttribute('version_no') === null) {
                $account->setAttribute('version_no', 1);
            }
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    public function evidenceDocument(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'evidence_document_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function lastEditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_edited_by');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }

    public function editors(): HasMany
    {
        return $this->hasMany(SupplierBankAccountEditor::class, 'bank_account_id');
    }

    public function hasContributor(int $userId): bool
    {
        return $this->editors()->where('user_id', $userId)->exists()
            || (int) $this->created_by === $userId;
    }

    /**
     * @param  'creator'|'editor'  $role
     */
    public function recordEditor(int $userId, string $role): void
    {
        $exists = $this->editors()->where('user_id', $userId)->exists();
        if ($exists) {
            return;
        }

        $this->editors()->create([
            'user_id' => $userId,
            'role' => $role,
            'first_edited_at' => now(),
        ]);
    }

    /**
     * Persist encrypted identifiers + fingerprint. Plaintext never becomes fillable mass-assignment.
     */
    public function storeEncryptedIdentifiers(
        ?string $ibanPlain,
        ?string $accountPlain,
        ?string $ibanFingerprint,
    ): void {
        $this->forceFill([
            'iban_ciphertext' => $ibanPlain,
            'account_ciphertext' => $accountPlain,
            'iban_fingerprint' => $ibanFingerprint,
        ])->save();
    }

    public function ibanFingerprintValue(): ?string
    {
        $value = $this->getAttributes()['iban_fingerprint'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Safe public projection — never includes plaintext or ciphertext identifiers.
     *
     * @return array<string, mixed>
     */
    public function toMaskedArray(): array
    {
        return [
            'id' => $this->id,
            'account_key' => $this->account_key,
            'version_no' => $this->version_no,
            'label' => $this->label,
            'account_holder_name' => $this->account_holder_name,
            'bank_name' => $this->bank_name,
            'iban_masked' => $this->iban_last4 !== null ? '****'.$this->iban_last4 : null,
            'account_masked' => $this->account_last4 !== null ? '****'.$this->account_last4 : null,
            'currency' => $this->currency,
            'status' => $this->status?->value ?? $this->status,
            'is_primary' => (bool) $this->is_primary,
            'is_current' => (int) $this->current_slot === 0,
            'supersedes_id' => $this->supersedes_id,
            'evidence_document_id' => $this->evidence_document_id,
            'created_by' => $this->created_by,
            'verified_by' => $this->verified_by,
            'verified_at' => $this->verified_at?->toIso8601String(),
        ];
    }
}
