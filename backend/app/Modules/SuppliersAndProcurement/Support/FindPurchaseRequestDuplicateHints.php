<?php

namespace App\Modules\SuppliersAndProcurement\Support;

use App\Modules\SuppliersAndProcurement\Enums\PurchaseRequestStatus;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequest;
use App\Modules\SuppliersAndProcurement\Models\PurchaseRequestLine;
use Illuminate\Support\Carbon;

/**
 * Soft duplicate detection (III-D6). Similarity warning only — never hard-blocks alone.
 */
final class FindPurchaseRequestDuplicateHints
{
    private const LOOKBACK_DAYS = 30;

    /**
     * @return list<array{purchase_request_id: int, pr_number: string, matched_line_nos: list<int>}>
     */
    public function forPurchaseRequest(PurchaseRequest $pr): array
    {
        $lines = $pr->relationLoaded('lines') ? $pr->lines : $pr->lines()->get();
        if ($lines->isEmpty()) {
            return [];
        }

        $probe = $lines->map(fn (PurchaseRequestLine $line): array => [
            'line_no' => (int) $line->line_no,
            'description' => (string) $line->description,
            'need_date' => $line->need_date?->toDateString() ?? '',
        ])->all();

        return $this->matchAgainstCandidates(
            $probe,
            $pr->season_id !== null ? (int) $pr->season_id : null,
            (int) $pr->id,
        );
    }

    /**
     * Draft-payload mode for GET /purchase-requests/duplicate-hints (API §15).
     *
     * @param  array{season_id?: int|null, lines?: list<array{line_no?: int, description?: string, need_date?: string}>}  $payload
     * @return list<array{purchase_request_id: int, pr_number: string, matched_line_nos: list<int>}>
     */
    public function forDraftPayload(array $payload): array
    {
        $rawLines = is_array($payload['lines'] ?? null) ? $payload['lines'] : [];
        $probe = [];
        $index = 1;
        foreach ($rawLines as $row) {
            if (! is_array($row)) {
                continue;
            }
            $description = trim((string) ($row['description'] ?? ''));
            $needDate = trim((string) ($row['need_date'] ?? ''));
            if ($description === '' || $needDate === '') {
                continue;
            }
            $probe[] = [
                'line_no' => isset($row['line_no']) ? (int) $row['line_no'] : $index,
                'description' => $description,
                'need_date' => $needDate,
            ];
            $index++;
        }

        if ($probe === []) {
            return [];
        }

        $seasonId = isset($payload['season_id']) && $payload['season_id'] !== null && $payload['season_id'] !== ''
            ? (int) $payload['season_id']
            : null;

        return $this->matchAgainstCandidates($probe, $seasonId, null);
    }

    /**
     * @param  list<array{line_no: int, description: string, need_date: string}>  $probeLines
     * @return list<array{purchase_request_id: int, pr_number: string, matched_line_nos: list<int>}>
     */
    private function matchAgainstCandidates(array $probeLines, ?int $seasonId, ?int $excludePrId): array
    {
        $since = Carbon::now()->subDays(self::LOOKBACK_DAYS)->toDateString();
        $hints = [];

        $candidates = PurchaseRequest::query()
            ->when($excludePrId !== null, fn ($q) => $q->whereKeyNot($excludePrId))
            ->whereIn('status', [
                PurchaseRequestStatus::Submitted,
                PurchaseRequestStatus::UnderReview,
                PurchaseRequestStatus::Approved,
                PurchaseRequestStatus::Returned,
            ])
            ->when($seasonId !== null, fn ($q) => $q->where('season_id', $seasonId))
            ->with('lines')
            ->limit(50)
            ->get();

        foreach ($candidates as $other) {
            $matched = [];
            foreach ($probeLines as $line) {
                $needDate = Carbon::parse($line['need_date'])->startOfDay();
                foreach ($other->lines as $otherLine) {
                    /** @var PurchaseRequestLine $otherLine */
                    $needClose = abs($needDate->diffInDays($otherLine->need_date->startOfDay(), false)) <= 7
                        || ($otherLine->need_date->toDateString() >= $since);
                    $descSimilar = mb_strtolower(trim($line['description'])) !== ''
                        && mb_strtolower(trim($line['description'])) === mb_strtolower(trim((string) $otherLine->description));
                    if ($descSimilar && $needClose) {
                        $matched[] = (int) $line['line_no'];
                        break;
                    }
                }
            }

            if ($matched !== []) {
                $hints[] = [
                    'purchase_request_id' => (int) $other->id,
                    'pr_number' => (string) $other->pr_number,
                    'matched_line_nos' => array_values(array_unique($matched)),
                ];
            }
        }

        return $hints;
    }
}
