<?php

namespace App\Modules\SeasonalTemplates\Adapters;

use App\Models\User;
use App\Modules\Contracts\Actions\CreateContract;
use App\Modules\Contracts\Actions\UpdateContract;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Models\ContractCategory;
use App\Modules\SeasonalTemplates\Support\SafeCopyAllowlist;
use App\Modules\SeasonalTemplates\Support\SafeCopyContext;
use App\Modules\SeasonalTemplates\Support\SafeCopyProvenanceStore;
use App\Modules\SeasonPilgrims\Models\Season;

/**
 * contract_definitions (X02, snapshot-only): title, contract_category_id,
 * counterparty_kind, notes, currency, start_date / end_date (remapped).
 *
 * Contracts are not season-scoped, so there is no live source: rows come from a
 * template snapshot and target rows are the contracts a previous copy created for
 * the target season (found through provenance). Every contract is created through
 * {@see CreateContract} — always `draft`, with a placeholder counterparty name.
 * Counterparty name, value, employee / supplier / organization unit, status,
 * numbers, signatures and attachments are never copied.
 */
final class ContractDefinitionsSafeCopyAdapter extends AbstractSafeCopyAdapter
{
    public const COUNTERPARTY_PLACEHOLDER = 'من القالب';

    public function __construct(
        SafeCopyProvenanceStore $provenance,
        private readonly CreateContract $create,
        private readonly UpdateContract $update,
    ) {
        parent::__construct($provenance);
    }

    public function entityKind(): string
    {
        return SafeCopyAllowlist::CONTRACT_DEFINITIONS;
    }

    public function supportsLiveSource(): bool
    {
        return false;
    }

    protected function requiredDateFields(): array
    {
        return ['start_date'];
    }

    protected function dateRangeFields(): array
    {
        return ['start_date', 'end_date'];
    }

    protected function sourceCandidates(Season $source): array
    {
        return [];
    }

    protected function targetRows(Season $target): array
    {
        $provenance = $this->provenance->forTargetSeason($this->entityKind(), $target->id);
        if ($provenance === []) {
            return [];
        }

        $contracts = Contract::query()
            ->whereIn('id', array_map(static fn ($row): int => $row->target_row_id, $provenance))
            ->get()
            ->keyBy('id');

        $rows = [];
        foreach ($provenance as $code => $row) {
            /** @var Contract|null $contract */
            $contract = $contracts->get($row->target_row_id);
            if ($contract === null) {
                continue;
            }

            $rows[(string) $code] = [
                'id' => $contract->id,
                'fields' => $this->allowlisted([
                    'code' => (string) $code,
                    'title' => $contract->title,
                    'contract_category_id' => $contract->contract_category_id,
                    'counterparty_kind' => $contract->counterparty_kind->value,
                    'notes' => $contract->notes,
                    'currency' => $contract->currency,
                    'start_date' => $contract->start_date->toDateString(),
                    'end_date' => $contract->end_date?->toDateString(),
                ]),
                'locked' => $contract->status !== ContractStatus::Draft,
            ];
        }

        return $rows;
    }

    protected function dependencyConflict(Season $target, string $key, array $fields, array $selection): ?array
    {
        $category = ContractCategory::query()->whereKey((int) $fields['contract_category_id'])->first();

        if ($category !== null && $category->isActive()) {
            return null;
        }

        return ['dependency' => 'contract_categories', 'contract_category_id' => (int) $fields['contract_category_id']];
    }

    protected function insertRow(SafeCopyContext $context, array $fields, User $actor): ?int
    {
        $contract = $this->create->execute($actor, [
            'title' => $fields['title'],
            'contract_category_id' => $fields['contract_category_id'],
            'counterparty_name' => self::COUNTERPARTY_PLACEHOLDER,
            'counterparty_kind' => $fields['counterparty_kind'],
            'currency' => $fields['currency'],
            'notes' => $fields['notes'],
            'start_date' => $fields['start_date'],
            'end_date' => $fields['end_date'],
        ], $context->request);

        return $contract->id;
    }

    protected function updateRow(SafeCopyContext $context, int $targetId, array $fields, User $actor): void
    {
        $this->update->execute($actor, Contract::query()->findOrFail($targetId), [
            'title' => $fields['title'],
            'contract_category_id' => $fields['contract_category_id'],
            'counterparty_kind' => $fields['counterparty_kind'],
            'currency' => $fields['currency'],
            'notes' => $fields['notes'],
            'start_date' => $fields['start_date'],
            'end_date' => $fields['end_date'],
        ], $context->request);
    }
}
