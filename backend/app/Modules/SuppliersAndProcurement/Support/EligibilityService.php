<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Core\Tenancy\TenantContext;
use App\Modules\Documents\Enums\DocumentLinkableType;
use App\Modules\Documents\Models\Document;
use App\Modules\SuppliersAndProcurement\Enums\DocumentVerificationStatus;
use App\Modules\SuppliersAndProcurement\Enums\EligibilityConfigStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\Supplier;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRecord;
use App\Modules\SuppliersAndProcurement\Models\SupplierDocumentRequirementDef;
use App\Modules\SuppliersAndProcurement\Models\SupplierEligibilityRequirementSet;
use Illuminate\Support\Collection;

/**
 * Shared, fail-closed supplier eligibility evaluation (S04 Phase II-A).
 *
 * Rules: C1 (approved immutable config only), C2 (type/country/category
 * applicability with requirement_key dedupe), C4 (live expiry recomputed from
 * `expires_at` in the tenant calendar; stored status never overrides it).
 * Banking is intentionally ignored (II-D2). Future use-time consumers (e.g.
 * purchase requests) must call this service again — never trust a cached result.
 */
final class EligibilityService
{
    public const REASON_CONFIG_MISSING = 'SUPPLIER_ELIGIBILITY_CONFIG_MISSING';

    public const REASON_CONFIG_DRAFT = 'SUPPLIER_ELIGIBILITY_CONFIG_DRAFT';

    public const REASON_NO_MANDATORY_REQUIREMENTS = 'SUPPLIER_ELIGIBILITY_NO_MANDATORY_REQUIREMENTS';

    public const REASON_DOCUMENT_EVIDENCE_INVALID = 'SUPPLIER_DOCUMENT_EVIDENCE_INVALID';

    public const REASON_DOCUMENT_MISSING = 'SUPPLIER_DOCUMENT_MISSING';

    public const REASON_DOCUMENT_EXPIRED = 'SUPPLIER_DOCUMENT_EXPIRED';

    public const REASON_DOCUMENT_REJECTED = 'SUPPLIER_DOCUMENT_REJECTED';

    public const REASON_DOCUMENT_PENDING = 'SUPPLIER_DOCUMENT_PENDING_VERIFICATION';

    private const FALLBACK_TIMEZONE = 'Asia/Riyadh';

    public function __construct(
        private readonly TenantContext $tenantContext,
    ) {}

    /**
     * @return array{
     *   eligible: bool,
     *   supplier_status: string,
     *   checked_at: string,
     *   reasons: list<array<string, mixed>>,
     *   applicable_requirement_keys: list<string>,
     *   config: array{id: int, config_key: string, version_no: int, status: string}|null,
     *   configs: list<array{id: int, config_key: string, version_no: int, status: string}>,
     *   satisfying_record_ids: list<int>
     * }
     */
    public function evaluate(Supplier $supplier, bool $lockEvidence = false): array
    {
        $tenant = $this->tenantContext->require();
        $timezone = $tenant->timezone !== null && $tenant->timezone !== '' ? $tenant->timezone : self::FALLBACK_TIMEZONE;
        $now = now($timezone);
        $today = $now->toDateString();

        $result = [
            'eligible' => false,
            'supplier_status' => $supplier->status->value,
            'checked_at' => $now->copy()->utc()->toIso8601String(),
            'reasons' => [],
            'applicable_requirement_keys' => [],
            'config' => null,
            'configs' => [],
            'satisfying_record_ids' => [],
        ];

        $sets = $this->latestApprovedSets();
        if ($sets->isEmpty()) {
            $hasDraft = SupplierEligibilityRequirementSet::query()
                ->where('status', EligibilityConfigStatus::Draft->value)
                ->exists();
            $result['reasons'][] = ['code' => $hasDraft ? self::REASON_CONFIG_DRAFT : self::REASON_CONFIG_MISSING];

            return $result;
        }

        $defs = $this->applicableDefs($supplier, $sets);
        if ($defs->isEmpty()) {
            // Zero applicable approved definitions => ineligible (no implicit exemption).
            $result['reasons'][] = ['code' => self::REASON_CONFIG_MISSING];

            return $result;
        }

        $contributing = $sets->whereIn('id', $defs->pluck('requirement_set_id')->unique()->all())->sortBy('id')->values();
        $result['configs'] = $contributing->map(fn (SupplierEligibilityRequirementSet $s): array => $this->snapshot($s))->all();
        $result['config'] = $result['configs'][count($result['configs']) - 1];
        $result['applicable_requirement_keys'] = $defs->pluck('requirement_key')->unique()->sort()->values()->all();

        $mandatoryKeys = $defs->where('is_mandatory', true)->pluck('requirement_key')->unique()->sort()->values();

        if ($mandatoryKeys->isEmpty()) {
            // M1=A (Owner): applicable requirements that are all optional can never make a
            // supplier eligible — fail closed, never silently eligible.
            $result['reasons'][] = ['code' => self::REASON_NO_MANDATORY_REQUIREMENTS];

            return $result;
        }

        $recordsQuery = SupplierDocumentRecord::query()
            ->where('supplier_id', $supplier->id)
            ->where('verification_status', '!=', DocumentVerificationStatus::Superseded->value)
            ->orderByDesc('id');
        if ($lockEvidence) {
            $recordsQuery->lockForUpdate();
        }
        $loadedRecords = $recordsQuery->get();

        // H1: always read the backing Documents fresh (never a cached relation). During
        // activation they are locked FOR UPDATE so a concurrent archive/relink cannot slip
        // between this check and the status change.
        $documentsQuery = Document::query()->whereIn('id', $loadedRecords->pluck('document_id')->unique()->all());
        if ($lockEvidence) {
            $documentsQuery->lockForUpdate();
        }
        $documents = $documentsQuery->get()->keyBy('id');
        $loadedRecords->each(fn (SupplierDocumentRecord $r) => $r->setRelation('document', $documents->get($r->document_id)));

        $records = $loadedRecords->groupBy(fn (SupplierDocumentRecord $r): string => (string) $r->requirement_key);

        foreach ($mandatoryKeys as $key) {
            /** @var Collection<int, SupplierDocumentRecord> $forKey */
            $forKey = $records->get($key, collect());

            $satisfying = $forKey->first(fn (SupplierDocumentRecord $r): bool => $this->isEffectivelyValid($r, $today));
            if ($satisfying !== null) {
                $result['satisfying_record_ids'][] = (int) $satisfying->id;

                continue;
            }

            $result['reasons'][] = $this->failureReason($key, $forKey, $today);
        }

        $result['eligible'] = $result['reasons'] === [];

        return $result;
    }

    /**
     * @return array<string, mixed> the passing evaluation (see {@see evaluate()})
     *
     * @throws SupplierDomainException SUPPLIER_NOT_ELIGIBLE with reasons[]
     */
    public function assertActivatable(Supplier $supplier): array
    {
        // Must run inside the activation transaction: evidence is re-read fresh and locked.
        $evaluation = $this->evaluate($supplier, lockEvidence: true);

        if (! $evaluation['eligible']) {
            throw SupplierDomainException::notEligibleWithReasons($evaluation['reasons']);
        }

        return $evaluation;
    }

    /**
     * A record satisfies eligibility only if ALL hold (H1): verified (valid, or stale
     * `expired`) AND live expiry OK in the tenant calendar AND not superseded AND its
     * backing Document is intact (see {@see hasIntactEvidence()}).
     * Expiry is inclusive through `expires_at`: expired only when tenant-local today > expires_at.
     */
    public function isEffectivelyValid(SupplierDocumentRecord $record, string $tenantToday): bool
    {
        return $this->isVerified($record)
            && ! $this->isPastExpiry($record, $tenantToday)
            && $this->hasIntactEvidence($record);
    }

    private function isVerified(SupplierDocumentRecord $record): bool
    {
        return in_array($record->verification_status, [
            DocumentVerificationStatus::Valid,
            DocumentVerificationStatus::Expired,
        ], true);
    }

    /**
     * Backing Document exists (tenant-scoped read => cross-tenant ids resolve to null), is
     * active, belongs to the record's tenant, and is still linked to THIS supplier.
     * The relation must have been loaded fresh by the caller.
     */
    public function hasIntactEvidence(SupplierDocumentRecord $record): bool
    {
        $document = $record->relationLoaded('document') ? $record->document : $record->document()->first();

        return $document !== null
            && (int) $document->tenant_id === (int) $record->tenant_id
            && $document->status->isActive()
            && $document->linkable_type === DocumentLinkableType::Supplier->value
            && (int) $document->linkable_id === (int) $record->supplier_id;
    }

    public function isPastExpiry(SupplierDocumentRecord $record, string $tenantToday): bool
    {
        if ($record->expires_at === null) {
            return false;
        }

        return $tenantToday > $record->expires_at->format('Y-m-d');
    }

    /**
     * Latest approved version per config_key (C1: only approved versions count).
     *
     * @return Collection<int, SupplierEligibilityRequirementSet>
     */
    private function latestApprovedSets(): Collection
    {
        return SupplierEligibilityRequirementSet::query()
            ->where('status', EligibilityConfigStatus::Approved->value)
            ->orderByDesc('version_no')
            ->get()
            ->unique('config_key')
            ->values();
    }

    /**
     * @param  Collection<int, SupplierEligibilityRequirementSet>  $sets
     * @return Collection<int, SupplierDocumentRequirementDef>
     */
    private function applicableDefs(Supplier $supplier, Collection $sets): Collection
    {
        $categoryIds = $supplier->categories()->pluck('supplier_categories.id')->map(fn ($id): int => (int) $id)->all();
        $country = strtoupper((string) $supplier->country_code);

        return SupplierDocumentRequirementDef::query()
            ->whereIn('requirement_set_id', $sets->pluck('id')->all())
            ->where('supplier_type', $supplier->supplier_type->value)
            ->where(function ($query) use ($country): void {
                $query->whereNull('country_code')->orWhere('country_code', $country);
            })
            ->where(function ($query) use ($categoryIds): void {
                $query->whereNull('supplier_category_id');
                if ($categoryIds !== []) {
                    $query->orWhereIn('supplier_category_id', $categoryIds);
                }
            })
            ->get();
    }

    /**
     * @param  Collection<int, SupplierDocumentRecord>  $forKey  newest first, non-superseded
     * @return array<string, mixed>
     */
    private function failureReason(string $key, Collection $forKey, string $today): array
    {
        // A verified-but-past-expiry record is the most informative failure.
        $expired = $forKey->first(fn (SupplierDocumentRecord $r): bool => in_array($r->verification_status, [
            DocumentVerificationStatus::Valid,
            DocumentVerificationStatus::Expired,
        ], true) && $this->isPastExpiry($r, $today));

        if ($expired !== null) {
            return [
                'code' => self::REASON_DOCUMENT_EXPIRED,
                'requirement_key' => $key,
                'expires_at' => $expired->expires_at?->format('Y-m-d'),
            ];
        }

        // Verified and unexpired but its evidence Document was archived/unlinked/reassigned (H1).
        $brokenEvidence = $forKey->first(fn (SupplierDocumentRecord $r): bool => $this->isVerified($r) && ! $this->hasIntactEvidence($r));
        if ($brokenEvidence !== null) {
            return ['code' => self::REASON_DOCUMENT_EVIDENCE_INVALID, 'requirement_key' => $key];
        }

        $latest = $forKey->first();
        if ($latest === null) {
            return ['code' => self::REASON_DOCUMENT_MISSING, 'requirement_key' => $key];
        }

        return match ($latest->verification_status) {
            DocumentVerificationStatus::Rejected => ['code' => self::REASON_DOCUMENT_REJECTED, 'requirement_key' => $key],
            default => ['code' => self::REASON_DOCUMENT_PENDING, 'requirement_key' => $key],
        };
    }

    /**
     * @return array{id: int, config_key: string, version_no: int, status: string}
     */
    private function snapshot(SupplierEligibilityRequirementSet $set): array
    {
        return [
            'id' => (int) $set->id,
            'config_key' => $set->config_key,
            'version_no' => (int) $set->version_no,
            'status' => $set->status->value,
        ];
    }
}
