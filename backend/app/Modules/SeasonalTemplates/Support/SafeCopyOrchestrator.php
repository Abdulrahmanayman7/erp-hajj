<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonalTemplates\Adapters\AbstractSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\AccommodationSeasonFacilitiesSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonPilgrimGroupsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\SeasonProgramsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\TransportEntitlementsSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Adapters\TransportSeasonVehiclesSafeCopyAdapter;
use App\Modules\SeasonalTemplates\Enums\CopyMode;
use App\Modules\SeasonalTemplates\Enums\CopyRunStatus;
use App\Modules\SeasonalTemplates\Enums\PreviewStatus;
use App\Modules\SeasonalTemplates\Enums\TemplateStatus;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreview;
use App\Modules\SeasonalTemplates\Models\SeasonCopyPreviewLine;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRun;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRunLine;
use App\Modules\SeasonalTemplates\Models\SeasonTemplate;
use App\Modules\SeasonPilgrims\Enums\SeasonStatus;
use App\Modules\SeasonPilgrims\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Central safe-copy engine (S08-D07): tenancy, allowlist, mandatory preview,
 * stale-preview rejection, atomic execution, run history and audit.
 *
 * Deny by default: only kinds in {@see SafeCopyAllowlist} that also have a
 * registered adapter can be planned or applied.
 */
final class SafeCopyOrchestrator
{
    public const PREVIEW_TTL_MINUTES = 30;

    public const DATE_STRATEGY = 'season_start_delta';

    /** Conflict codes for which an explicit `overwrite` decision is allowed. */
    private const OVERWRITABLE_CONFLICTS = ['DUPLICATE_KEY', 'MANUAL_MODIFIED'];

    /**
     * @var array<string, SafeCopyAdapter>
     */
    private array $adapters = [];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly CorrelationId $correlationId,
        private readonly SeasonCopyRunNumberGenerator $runNumbers,
        private readonly SafeCopyProvenanceStore $provenance,
        SeasonProgramsSafeCopyAdapter $programs,
        SeasonPilgrimGroupsSafeCopyAdapter $groups,
        TransportEntitlementsSafeCopyAdapter $entitlements,
        TransportSeasonVehiclesSafeCopyAdapter $vehicles,
        AccommodationSeasonFacilitiesSafeCopyAdapter $facilities,
    ) {
        foreach ([$programs, $groups, $entitlements, $vehicles, $facilities] as $adapter) {
            $this->adapters[$adapter->entityKind()] = $adapter;
        }
    }

    /**
     * @param  array<string, mixed>  $input  mode, source_season_id, target_season_id, source_template_id?,
     *                                       selection{entity_kinds?, codes?}, date_mapping{strategy?}
     */
    public function createPreview(User $actor, array $input, Request $request): SeasonCopyPreview
    {
        $tenant = $this->tenantContext->require();

        $mode = CopyMode::from((string) $input['mode']);
        $source = Season::query()->findOrFail((int) $input['source_season_id']);
        $target = Season::query()->findOrFail((int) $input['target_season_id']);
        $this->assertSeasons($source, $target);

        $template = $mode === CopyMode::FromTemplate
            ? $this->loadUsableTemplate((int) ($input['source_template_id'] ?? 0))
            : null;

        $selection = $this->buildSelection($mode, $template, $input);
        $dateMapping = $this->dateMapping($source, $target);
        $built = $this->buildPlan($source, $target, $selection);

        $contentHash = $this->contentHash(
            $mode,
            $source->id,
            $target->id,
            $template?->id,
            $selection,
            $dateMapping,
            $built['lines'],
        );

        return DB::transaction(function () use ($actor, $request, $tenant, $mode, $source, $target, $template, $selection, $dateMapping, $built, $contentHash): SeasonCopyPreview {
            $preview = new SeasonCopyPreview([
                'preview_token' => (string) Str::uuid(),
                'mode' => $mode,
                'source_season_id' => $source->id,
                'source_template_id' => $template?->id,
                'target_season_id' => $target->id,
                'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
                'content_hash' => $contentHash,
                'source_fingerprint' => $this->seasonFingerprint($source),
                'target_fingerprint' => $this->seasonFingerprint($target),
                'adapter_fingerprints_json' => $built['fingerprints'],
                'date_mapping_json' => $dateMapping,
                'selection_json' => $selection,
                'counts_json' => $built['counts'],
                'status' => PreviewStatus::Valid,
                'expires_at' => now()->addMinutes(self::PREVIEW_TTL_MINUTES),
                'created_by' => $actor->id,
            ]);
            $preview->save();

            foreach ($built['lines'] as $line) {
                $summary = $line['summary'];
                if ($line['reason_code'] !== null) {
                    $summary['reason_code'] = $line['reason_code'];
                }

                (new SeasonCopyPreviewLine([
                    'preview_id' => $preview->id,
                    'entity_kind' => $line['entity_kind'],
                    'action' => $line['action'],
                    'natural_key' => $line['natural_key'],
                    'summary_json' => $summary,
                    'conflict_code' => $line['conflict_code'],
                    'proposed_resolution' => null,
                ]))->save();
            }

            $this->security->record(AuthorizationSecurityEvent::SEASON_COPY_PREVIEW_CREATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'season_copy_preview',
                'entity_id' => $preview->id,
                'mode' => $mode->value,
                'source_season_id' => $source->id,
                'target_season_id' => $target->id,
                'source_template_id' => $template?->id,
                'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
                'entity_kinds' => $selection['entity_kinds'],
                'counts' => $built['counts'],
            ], $request);

            return $preview->load('lines');
        });
    }

    /**
     * @param  array<string, mixed>  $input  preview_token, content_hash, resolutions[]{entity_kind, natural_key, decision}
     */
    public function executePreview(User $actor, array $input, Request $request): SeasonCopyRun
    {
        $this->tenantContext->require();

        $token = (string) ($input['preview_token'] ?? '');
        if ($token === '' || (string) ($input['content_hash'] ?? '') === '') {
            throw SeasonalTemplatesDomainException::previewRequired();
        }

        // Tenant-scoped lookup: another tenant's token is indistinguishable from unknown (404).
        $preview = SeasonCopyPreview::query()->where('preview_token', $token)->firstOrFail();
        $startedAt = now();

        try {
            return DB::transaction(
                fn (): SeasonCopyRun => $this->runExecution($actor, $preview, $input, $request, $startedAt),
            );
        } catch (Throwable $e) {
            $this->recordFailure($actor, $preview, $e, $startedAt, $request);

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function runExecution(User $actor, SeasonCopyPreview $stored, array $input, Request $request, Carbon $startedAt): SeasonCopyRun
    {
        $tenant = $this->tenantContext->require();

        // Serialize executions of the same preview and target season.
        $preview = SeasonCopyPreview::query()->whereKey($stored->id)->lockForUpdate()->firstOrFail();
        $target = Season::query()->whereKey($preview->target_season_id)->lockForUpdate()->firstOrFail();
        $source = Season::query()->findOrFail($preview->source_season_id);

        $provided = (string) ($input['content_hash'] ?? '');
        if ($provided === '' || ! hash_equals($preview->content_hash, $provided)) {
            throw SeasonalTemplatesDomainException::previewStale('HASH_MISMATCH');
        }
        if ($preview->status !== PreviewStatus::Valid) {
            throw SeasonalTemplatesDomainException::previewStale('NOT_VALID');
        }
        if ($preview->expires_at->isPast()) {
            throw SeasonalTemplatesDomainException::previewStale('EXPIRED');
        }
        if ($preview->matrix_version !== SafeCopyAllowlist::MATRIX_VERSION) {
            throw SeasonalTemplatesDomainException::previewStale('MATRIX_VERSION');
        }

        $this->assertSeasons($source, $target);

        $template = null;
        if ($preview->mode === CopyMode::FromTemplate) {
            $template = SeasonTemplate::query()->with('items')->find($preview->source_template_id);
            if (
                $template === null
                || $template->status !== TemplateStatus::Approved
                || $template->matrix_version !== SafeCopyAllowlist::MATRIX_VERSION
            ) {
                throw SeasonalTemplatesDomainException::previewStale('TEMPLATE_CHANGED');
            }
        }

        /** @var array{entity_kinds: list<string>, codes: array<string, list<string>>} $selection */
        $selection = $preview->selection_json;
        $built = $this->buildPlan($source, $target, $selection);

        $hash = $this->contentHash(
            $preview->mode,
            $source->id,
            $target->id,
            $preview->source_template_id,
            $selection,
            $preview->date_mapping_json ?? [],
            $built['lines'],
        );

        if (
            ! hash_equals($preview->content_hash, $hash)
            || ! hash_equals($preview->source_fingerprint, $this->seasonFingerprint($source))
            || ! hash_equals($preview->target_fingerprint, $this->seasonFingerprint($target))
            || $preview->adapter_fingerprints_json !== $built['fingerprints']
        ) {
            throw SeasonalTemplatesDomainException::previewStale('CONTENT_CHANGED');
        }

        $directives = $this->directives($built['lines'], $input['resolutions'] ?? []);

        $run = new SeasonCopyRun([
            'run_number' => $this->runNumbers->next(),
            'preview_id' => $preview->id,
            'mode' => $preview->mode,
            'source_season_id' => $source->id,
            'source_template_id' => $preview->source_template_id,
            'target_season_id' => $target->id,
            'status' => CopyRunStatus::Succeeded,
            'correlation_id' => $this->correlationId->get() ?? (string) Str::uuid(),
            'started_at' => $startedAt,
            'created_by' => $actor->id,
        ]);
        $run->save();

        $counts = ['added' => 0, 'changed' => 0, 'skipped' => 0, 'excluded' => 0];

        foreach ($selection['entity_kinds'] as $kind) {
            foreach ($built['lines'] as $line) {
                if ($line['entity_kind'] === $kind && $line['action'] === 'exclude') {
                    $this->recordRunLine($run, $kind, $line['natural_key'], 'excluded', $line['reason_code'], $line['source_id'], $line['target_id']);
                    $counts['excluded']++;
                }
            }

            if (! isset($directives[$kind])) {
                continue;
            }

            $results = $this->adapter($kind)->apply($source, $target, $directives[$kind], $run->id, $actor);

            foreach ($results as $result) {
                $this->recordRunLine(
                    $run,
                    $kind,
                    $result['natural_key'],
                    $result['result'],
                    $result['reason_code'],
                    $result['source_id'],
                    $result['target_id'],
                );

                match ($result['result']) {
                    'added' => $counts['added']++,
                    'changed', 'overwritten' => $counts['changed']++,
                    default => $counts['skipped']++,
                };

                if ($result['copied_hash'] !== null && $result['target_id'] !== null) {
                    $this->provenance->record(
                        $kind,
                        $target->id,
                        $result['target_id'],
                        $source->id,
                        $preview->source_template_id,
                        $result['source_id'],
                        $run->id,
                        $result['copied_hash'],
                    );
                }
            }
        }

        $run->forceFill(['counts_json' => $counts, 'finished_at' => now()])->save();
        $preview->forceFill(['status' => PreviewStatus::Consumed])->save();

        $this->security->record(AuthorizationSecurityEvent::SEASON_COPY_EXECUTION_SUCCEEDED, [
            'tenant_id' => $tenant->id,
            'actor_id' => $actor->id,
            'entity_type' => 'season_copy_run',
            'entity_id' => $run->id,
            'entity_number' => $run->run_number,
            'preview_id' => $preview->id,
            'mode' => $preview->mode->value,
            'source_season_id' => $source->id,
            'target_season_id' => $target->id,
            'source_template_id' => $preview->source_template_id,
            'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
            'entity_kinds' => $selection['entity_kinds'],
            'counts' => $counts,
        ], $request);

        return $run->load('lines');
    }

    private function recordFailure(User $actor, SeasonCopyPreview $preview, Throwable $e, Carbon $startedAt, Request $request): void
    {
        try {
            $domain = $e instanceof SeasonalTemplatesDomainException ? $e : null;
            $code = $domain?->errorCode() ?? 'SEASON_COPY_EXECUTION_ERROR';
            $message = $domain?->getMessage() ?? 'تعذر تنفيذ النسخ. تم التراجع عن جميع التغييرات.';

            if ($domain?->errorCode() === SeasonalTemplatesDomainException::PREVIEW_STALE) {
                $expired = $domain->invalidationReason() === 'EXPIRED';
                SeasonCopyPreview::query()
                    ->whereKey($preview->id)
                    ->where('status', PreviewStatus::Valid->value)
                    ->update([
                        'status' => ($expired ? PreviewStatus::Expired : PreviewStatus::Invalidated)->value,
                        'invalidated_reason' => $domain->invalidationReason(),
                    ]);
            }

            DB::transaction(function () use ($actor, $preview, $code, $message, $startedAt, $request): void {
                $tenant = $this->tenantContext->require();

                $run = new SeasonCopyRun([
                    'run_number' => $this->runNumbers->next(),
                    'preview_id' => $preview->id,
                    'mode' => $preview->mode,
                    'source_season_id' => $preview->source_season_id,
                    'source_template_id' => $preview->source_template_id,
                    'target_season_id' => $preview->target_season_id,
                    'status' => CopyRunStatus::Failed,
                    'error_code' => $code,
                    'error_message' => mb_substr($message, 0, 255),
                    'counts_json' => ['added' => 0, 'changed' => 0, 'skipped' => 0, 'excluded' => 0],
                    'correlation_id' => $this->correlationId->get() ?? (string) Str::uuid(),
                    'started_at' => $startedAt,
                    'finished_at' => now(),
                    'created_by' => $actor->id,
                ]);
                $run->save();

                $this->security->record(AuthorizationSecurityEvent::SEASON_COPY_EXECUTION_FAILED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'season_copy_run',
                    'entity_id' => $run->id,
                    'entity_number' => $run->run_number,
                    'preview_id' => $preview->id,
                    'mode' => $preview->mode->value,
                    'source_season_id' => $preview->source_season_id,
                    'target_season_id' => $preview->target_season_id,
                    'error_code' => $code,
                ], $request);
            });
        } catch (Throwable $inner) {
            report($inner);
        }
    }

    /**
     * Source: draft|active|closed. Target: draft only. Distinct seasons.
     * Both seasons were loaded through the tenant scope, so they share the tenant.
     */
    private function assertSeasons(Season $source, Season $target): void
    {
        if ($source->id === $target->id) {
            throw SeasonalTemplatesDomainException::sourceInvalid('الموسم المصدر والهدف يجب أن يختلفا.');
        }

        if (! in_array($source->status, [SeasonStatus::Draft, SeasonStatus::Active, SeasonStatus::Closed], true)) {
            throw SeasonalTemplatesDomainException::sourceInvalid();
        }

        if ($target->status !== SeasonStatus::Draft) {
            throw SeasonalTemplatesDomainException::targetNotDraft();
        }
    }

    private function loadUsableTemplate(int $templateId): SeasonTemplate
    {
        $template = SeasonTemplate::query()->with('items')->findOrFail($templateId);

        if ($template->status !== TemplateStatus::Approved) {
            throw SeasonalTemplatesDomainException::sourceInvalid('القالب يجب أن يكون معتمداً.');
        }

        if ($template->matrix_version !== SafeCopyAllowlist::MATRIX_VERSION) {
            throw SeasonalTemplatesDomainException::sourceInvalid('إصدار مصفوفة النسخ في القالب قديم. أنشئ قالباً جديداً.');
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{entity_kinds: list<string>, codes: array<string, list<string>>}
     */
    private function buildSelection(CopyMode $mode, ?SeasonTemplate $template, array $input): array
    {
        /** @var array<string, mixed> $requested */
        $requested = is_array($input['selection'] ?? null) ? $input['selection'] : [];
        /** @var list<string> $requestedKinds */
        $requestedKinds = array_values(array_map('strval', (array) ($requested['entity_kinds'] ?? [])));

        $codes = [];
        if ($mode === CopyMode::FromTemplate && $template !== null) {
            $templateKinds = [];
            foreach ($template->items as $item) {
                $templateKinds[] = $item->entity_kind;
                $codes[$item->entity_kind] = (array) ($item->selection_json['codes'] ?? []);
            }

            foreach ($requestedKinds as $kind) {
                if (! in_array($kind, $templateKinds, true)) {
                    throw SeasonalTemplatesDomainException::kindDenied($kind);
                }
            }

            $kinds = $requestedKinds === [] ? $templateKinds : $requestedKinds;
        } else {
            $kinds = $requestedKinds;
            $codes = is_array($requested['codes'] ?? null) ? $requested['codes'] : [];
        }

        if ($kinds === []) {
            throw SeasonalTemplatesDomainException::sourceInvalid('لم يتم تحديد أنواع بيانات للنسخ.');
        }

        // Deny by default: every kind and every codes key must be approved.
        $ordered = SafeCopyAllowlist::ordered($kinds);
        foreach (array_keys($codes) as $kind) {
            SafeCopyAllowlist::assertApproved((string) $kind);
        }

        $normalizedCodes = [];
        foreach ($ordered as $kind) {
            $list = array_values(array_unique(array_map('strval', (array) ($codes[$kind] ?? []))));
            if ($list === []) {
                continue;
            }
            sort($list);
            $normalizedCodes[$kind] = $list;
        }

        return ['entity_kinds' => $ordered, 'codes' => $normalizedCodes];
    }

    /**
     * Date mapping (DT-01): none of the approved kinds carries a date field, so no
     * field is transformed; the anchors are recorded so a later season-date change
     * is visible to stale-preview detection.
     *
     * @return array<string, mixed>
     */
    private function dateMapping(Season $source, Season $target): array
    {
        return [
            'strategy' => self::DATE_STRATEGY,
            'source_anchor' => $source->operational_starts_on->toDateString(),
            'target_anchor' => $target->operational_starts_on->toDateString(),
            'delta_days' => (int) $source->operational_starts_on->diffInDays($target->operational_starts_on, false),
            'transformed_fields' => [],
        ];
    }

    /**
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @return array{
     *   lines: list<array{entity_kind: string, action: string, natural_key: string, source_id: int|null, target_id: int|null, conflict_code: string|null, reason_code: string|null, summary: array<string, mixed>}>,
     *   counts: array<string, int>,
     *   fingerprints: array<string, string>
     * }
     */
    private function buildPlan(Season $source, Season $target, array $selection): array
    {
        $lines = [];
        $counts = ['add' => 0, 'change' => 0, 'exclude' => 0, 'conflict' => 0];
        $fingerprints = [];

        foreach ($selection['entity_kinds'] as $kind) {
            $adapter = $this->adapter($kind);
            $plan = $adapter->plan($source, $target, $selection);
            $fingerprints[$kind] = $adapter->fingerprint($source, $target);

            $kindLines = [];
            foreach ([['adds', 'add'], ['changes', 'change'], ['excludes', 'exclude'], ['conflicts', 'conflict']] as [$bucket, $action]) {
                foreach ($plan[$bucket] as $entry) {
                    $counts[$action]++;
                    $kindLines[] = [
                        'entity_kind' => $kind,
                        'action' => $action,
                        'natural_key' => (string) $entry['natural_key'],
                        'source_id' => $entry['source_id'] ?? null,
                        'target_id' => $entry['target_id'] ?? null,
                        'conflict_code' => $entry['conflict_code'] ?? null,
                        'reason_code' => $entry['reason_code'] ?? null,
                        'summary' => $entry['summary'] ?? [],
                    ];
                }
            }

            usort($kindLines, static fn (array $a, array $b): int => strcmp($a['natural_key'], $b['natural_key']));
            array_push($lines, ...$kindLines);
        }

        return ['lines' => $lines, 'counts' => $counts, 'fingerprints' => $fingerprints];
    }

    /**
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @param  array<string, mixed>  $dateMapping
     * @param  list<array<string, mixed>>  $lines
     */
    private function contentHash(CopyMode $mode, int $sourceId, int $targetId, ?int $templateId, array $selection, array $dateMapping, array $lines): string
    {
        return CanonicalJson::hash([
            'matrix_version' => SafeCopyAllowlist::MATRIX_VERSION,
            'mode' => $mode->value,
            'source_season_id' => $sourceId,
            'target_season_id' => $targetId,
            'source_template_id' => $templateId,
            'selection' => $selection,
            'date_mapping' => $dateMapping,
            'lines' => $lines,
        ]);
    }

    private function seasonFingerprint(Season $season): string
    {
        return CanonicalJson::hash([
            'id' => $season->id,
            'status' => $season->status->value,
            'starts' => $season->operational_starts_on->toDateString(),
            'ends' => $season->operational_ends_on->toDateString(),
            'updated_at' => $season->updated_at?->toIso8601String(),
        ]);
    }

    /**
     * Build per-kind natural_key => directive maps. Every conflict needs an explicit decision.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array<string, array<string, string>>
     */
    private function directives(array $lines, mixed $resolutions): array
    {
        $decisions = [];
        foreach (is_array($resolutions) ? $resolutions : [] as $resolution) {
            if (! is_array($resolution)) {
                continue;
            }
            $decisions[($resolution['entity_kind'] ?? '')."\0".($resolution['natural_key'] ?? '')] = (string) ($resolution['decision'] ?? '');
        }

        $directives = [];
        foreach ($lines as $line) {
            $kind = (string) $line['entity_kind'];
            $key = (string) $line['natural_key'];

            if ($line['action'] === 'add' || $line['action'] === 'change') {
                $directives[$kind][$key] = AbstractSafeCopyAdapter::RESOLUTION_APPLY;

                continue;
            }

            if ($line['action'] !== 'conflict') {
                continue;
            }

            $decision = $decisions[$kind."\0".$key] ?? null;
            if (! in_array($decision, [AbstractSafeCopyAdapter::RESOLUTION_SKIP, AbstractSafeCopyAdapter::RESOLUTION_OVERWRITE], true)) {
                throw SeasonalTemplatesDomainException::conflictUnresolved();
            }

            if (
                $decision === AbstractSafeCopyAdapter::RESOLUTION_OVERWRITE
                && ! in_array($line['conflict_code'], self::OVERWRITABLE_CONFLICTS, true)
            ) {
                throw SeasonalTemplatesDomainException::conflictUnresolved('لا يمكن استبدال هذا التعارض؛ اختر التخطي.');
            }

            $directives[$kind][$key] = $decision;
        }

        return $directives;
    }

    private function recordRunLine(SeasonCopyRun $run, string $kind, string $key, string $result, ?string $reason, ?int $sourceId, ?int $targetId): void
    {
        (new SeasonCopyRunLine([
            'run_id' => $run->id,
            'entity_kind' => $kind,
            'natural_key' => $key,
            'result' => $result,
            'reason_code' => $reason,
            'source_row_id' => $sourceId,
            'target_row_id' => $targetId,
        ]))->save();
    }

    private function adapter(string $kind): SafeCopyAdapter
    {
        SafeCopyAllowlist::assertApproved($kind);

        return $this->adapters[$kind] ?? throw SeasonalTemplatesDomainException::kindDenied($kind);
    }
}
