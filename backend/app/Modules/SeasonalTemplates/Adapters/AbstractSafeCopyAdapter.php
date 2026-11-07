<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonCopyRowProvenance;
use App\Modules\SeasonalTemplates\Support\CanonicalJson;
use App\Modules\SeasonalTemplates\Support\SafeCopyAdapter;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonPilgrims\Models\Season;
use LogicException;

/**
 * Shared classification / idempotency / manual-protection algorithm.
 *
 * A concrete adapter only reads source rows, reads target rows, and inserts or
 * updates allowlisted fields — it can never carry a field outside
 * {@see SafeCopyAllowlist::fieldsFor()}.
 *
 * Per source row (matched to the target by natural key):
 *  - no target row                         → add
 *  - target equals source (copied fields)  → exclude (ALREADY_PRESENT_UNCHANGED)
 *  - differs, no provenance                → conflict DUPLICATE_KEY
 *  - differs, system-owned and unmodified  → change
 *  - differs, modified since copy          → conflict MANUAL_MODIFIED
 */
abstract class AbstractSafeCopyAdapter implements SafeCopyAdapter
{
    public const RESOLUTION_APPLY = 'apply';

    public const RESOLUTION_SKIP = 'skip';

    public const RESOLUTION_OVERWRITE = 'overwrite';

    public function __construct(
        protected readonly SafeCopyProvenanceStore $provenance,
    ) {}

    /**
     * @return array<string, array{source_id: int, fields: array<string, mixed>}> keyed by natural key
     */
    abstract protected function sourceCandidates(Season $source): array;

    /**
     * @return array<string, array{id: int, fields: array<string, mixed>}> keyed by natural key
     */
    abstract protected function targetRows(Season $target): array;

    /**
     * @param  array<string, mixed>  $fields
     * @return int|null new target row id, or null when a referenced master is missing
     */
    abstract protected function insertTarget(Season $target, array $fields, User $actor): ?int;

    /**
     * @param  array<string, mixed>  $fields  allowlisted non-key fields only
     */
    abstract protected function updateTarget(int $targetId, array $fields): void;

    /**
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>|null detail when a dependency is missing
     */
    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        return null;
    }

    public function fingerprint(Season $source, Season $target): string
    {
        $targets = $this->targetRows($target);
        $provenance = $this->provenance->forTargets(
            $this->entityKind(),
            array_values(array_map(static fn (array $row): int => $row['id'], $targets)),
        );

        return CanonicalJson::hash([
            'kind' => $this->entityKind(),
            'source' => $this->sourceCandidates($source),
            'target' => $targets,
            'provenance' => array_map(
                static fn (SeasonCopyRowProvenance $row): array => [
                    'system_owned' => $row->system_owned,
                    'copied_hash' => $row->copied_hash,
                ],
                $provenance,
            ),
        ]);
    }

    public function plan(Season $source, Season $target, array $selection): array
    {
        $plan = ['adds' => [], 'changes' => [], 'excludes' => [], 'conflicts' => []];

        $targets = $this->targetRows($target);
        $provenance = $this->provenance->forTargets(
            $this->entityKind(),
            array_values(array_map(static fn (array $row): int => $row['id'], $targets)),
        );

        foreach ($this->selected($this->sourceCandidates($source), $selection) as $key => $candidate) {
            $line = [
                'natural_key' => (string) $key,
                'source_id' => $candidate['source_id'],
                'summary' => [],
            ];

            $missing = $this->dependencyConflict($target, $key, $candidate['fields'], $selection);
            if ($missing !== null) {
                $plan['conflicts'][] = [
                    ...$line,
                    'conflict_code' => 'MISSING_DEPENDENCY',
                    'summary' => ['detail' => $missing],
                ];

                continue;
            }

            $targetRow = $targets[$key] ?? null;
            $classification = $this->classify(
                $candidate['fields'],
                $targetRow,
                $targetRow !== null ? ($provenance[$targetRow['id']] ?? null) : null,
            );

            switch ($classification['action']) {
                case 'add':
                    $plan['adds'][] = [...$line, 'summary' => ['fields' => $candidate['fields']]];
                    break;
                case 'change':
                    $plan['changes'][] = [
                        ...$line,
                        'target_id' => $targetRow['id'] ?? null,
                        'summary' => ['diff' => $classification['diff']],
                    ];
                    break;
                case 'exclude':
                    $plan['excludes'][] = [
                        ...$line,
                        'target_id' => $targetRow['id'] ?? null,
                        'reason_code' => 'ALREADY_PRESENT_UNCHANGED',
                    ];
                    break;
                default:
                    $plan['conflicts'][] = [
                        ...$line,
                        'target_id' => $targetRow['id'] ?? null,
                        'conflict_code' => $classification['conflict_code'],
                        'summary' => ['diff' => $classification['diff']],
                    ];
            }
        }

        return $plan;
    }

    public function apply(Season $source, Season $target, array $resolutions, int $runId, User $actor): array
    {
        $results = [];

        $targets = $this->targetRows($target);
        $provenance = $this->provenance->forTargets(
            $this->entityKind(),
            array_values(array_map(static fn (array $row): int => $row['id'], $targets)),
        );

        foreach ($this->sourceCandidates($source) as $key => $candidate) {
            if (! array_key_exists($key, $resolutions)) {
                continue;
            }

            $directive = $resolutions[$key];
            $sourceId = $candidate['source_id'];

            if ($directive === self::RESOLUTION_SKIP) {
                $results[] = $this->result($key, 'skipped', 'CONFLICT_SKIPPED', $sourceId, $targets[$key]['id'] ?? null);

                continue;
            }

            $targetRow = $targets[$key] ?? null;
            $classification = $this->classify(
                $candidate['fields'],
                $targetRow,
                $targetRow !== null ? ($provenance[$targetRow['id']] ?? null) : null,
            );

            $copiedHash = SafeCopyProvenanceStore::hashFields($candidate['fields']);
            $updateFields = $this->withoutKey($candidate['fields']);

            if ($directive === self::RESOLUTION_APPLY && $classification['action'] === 'add') {
                $targetId = $this->insertTarget($target, $candidate['fields'], $actor);
                $results[] = $targetId === null
                    ? $this->result($key, 'skipped', 'MISSING_DEPENDENCY', $sourceId, null)
                    : $this->result($key, 'added', null, $sourceId, $targetId, $copiedHash);

                continue;
            }

            if ($directive === self::RESOLUTION_APPLY && $classification['action'] === 'change') {
                $this->updateTarget((int) $targetRow['id'], $updateFields);
                $results[] = $this->result($key, 'changed', null, $sourceId, (int) $targetRow['id'], $copiedHash);

                continue;
            }

            if (
                $directive === self::RESOLUTION_OVERWRITE
                && $classification['action'] === 'conflict'
                && in_array($classification['conflict_code'], ['DUPLICATE_KEY', 'MANUAL_MODIFIED'], true)
            ) {
                $this->updateTarget((int) $targetRow['id'], $updateFields);
                $results[] = $this->result($key, 'overwritten', null, $sourceId, (int) $targetRow['id'], $copiedHash);

                continue;
            }

            // The world changed between plan and apply, or the directive is not valid
            // for the current classification: fail closed.
            throw SeasonalTemplatesDomainException::previewStale();
        }

        return $results;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  array{id: int, fields: array<string, mixed>}|null  $target
     * @return array{action: string, conflict_code?: string, diff: array<string, array{before: mixed, after: mixed}>}
     */
    private function classify(array $fields, ?array $target, ?SeasonCopyRowProvenance $provenance): array
    {
        if ($target === null) {
            return ['action' => 'add', 'diff' => []];
        }

        $diff = [];
        foreach ($this->withoutKey($fields) as $field => $value) {
            $existing = $target['fields'][$field] ?? null;
            if ($existing !== $value) {
                $diff[$field] = ['before' => $existing, 'after' => $value];
            }
        }

        if ($diff === []) {
            return ['action' => 'exclude', 'diff' => []];
        }

        if ($provenance === null) {
            return ['action' => 'conflict', 'conflict_code' => 'DUPLICATE_KEY', 'diff' => $diff];
        }

        if (
            $provenance->system_owned
            && hash_equals($provenance->copied_hash, SafeCopyProvenanceStore::hashFields($target['fields']))
        ) {
            return ['action' => 'change', 'diff' => $diff];
        }

        return ['action' => 'conflict', 'conflict_code' => 'MANUAL_MODIFIED', 'diff' => $diff];
    }

    /**
     * @param  array<string, array{source_id: int, fields: array<string, mixed>}>  $candidates
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     * @return array<string, array{source_id: int, fields: array<string, mixed>}>
     */
    private function selected(array $candidates, array $selection): array
    {
        $codes = $selection['codes'][$this->entityKind()] ?? null;
        if ($codes === null || in_array('*', $codes, true)) {
            return $candidates;
        }

        return array_filter(
            $candidates,
            static fn (int|string $key): bool => in_array((string) $key, $codes, true),
            ARRAY_FILTER_USE_KEY,
        );
    }

    /**
     * Whether $kind/$key is part of the same copy (so a dependency will exist at apply time).
     *
     * @param  array{entity_kinds: list<string>, codes: array<string, list<string>>}  $selection
     */
    protected function willBeCopied(array $selection, string $kind, string $key): bool
    {
        if (! in_array($kind, $selection['entity_kinds'], true)) {
            return false;
        }

        $codes = $selection['codes'][$kind] ?? null;

        return $codes === null || in_array('*', $codes, true) || in_array($key, $codes, true);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function withoutKey(array $fields): array
    {
        unset($fields[SafeCopyAllowlist::keyFieldFor($this->entityKind())]);

        return $fields;
    }

    /**
     * Enforce the allowlist: an adapter may only emit exactly the approved fields.
     *
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    protected function allowlisted(array $fields): array
    {
        $expected = SafeCopyAllowlist::fieldsFor($this->entityKind());
        $actual = array_keys($fields);
        sort($expected);
        sort($actual);

        if ($expected !== $actual) {
            throw new LogicException('Adapter '.static::class.' emitted fields outside the safe-copy allowlist.');
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @return array{natural_key: string, result: string, reason_code: string|null, source_id: int|null, target_id: int|null, copied_hash: string|null}
     */
    private function result(string $key, string $result, ?string $reason, ?int $sourceId, ?int $targetId, ?string $hash = null): array
    {
        return [
            'natural_key' => $key,
            'result' => $result,
            'reason_code' => $reason,
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'copied_hash' => $hash,
        ];
    }
}
