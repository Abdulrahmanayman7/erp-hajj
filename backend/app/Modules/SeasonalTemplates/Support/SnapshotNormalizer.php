<?php

namespace App\Modules\SeasonalTemplates\Support;

use App\Modules\SeasonalTemplates\Exceptions\SeasonalTemplatesDomainException;
use App\Modules\SeasonalTemplates\Models\SeasonReminderRule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Throwable;

/**
 * Validates a client-supplied template snapshot (X01) against the field allowlist.
 *
 * Deny by default: a row carrying any field outside
 * {@see SafeCopyAllowlist::fieldsFor()} is rejected — never silently trimmed — so a
 * forbidden field (value, assignee, budget, ...) can never be stored in a template.
 *
 * Input:  `{anchor?: 'Y-m-d', rows: list<array>}`
 * Output: `{anchor: string|null, rows: array<copy key, allowlisted fields>}`
 */
final class SnapshotNormalizer
{
    /**
     * @return array{anchor: string|null, rows: array<string, array<string, mixed>>}|null null when there are no rows
     */
    public function normalize(string $kind, mixed $snapshot): ?array
    {
        SafeCopyAllowlist::assertApproved($kind);

        if ($snapshot === null) {
            return null;
        }

        if (! is_array($snapshot)) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('صيغة اللقطة غير صالحة.');
        }

        $unknown = array_diff(array_keys($snapshot), ['anchor', 'rows']);
        if ($unknown !== []) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('حقول غير مسموحة في اللقطة.');
        }

        $anchor = $this->anchor($snapshot['anchor'] ?? null);
        $input = $snapshot['rows'] ?? [];
        if (! is_array($input)) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('صيغة صفوف اللقطة غير صالحة.');
        }

        if ($input === []) {
            return null;
        }

        if (count($input) > 500) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('عدد صفوف اللقطة يتجاوز الحد المسموح.');
        }

        $rows = [];
        $seen = [];
        $cleaned = [];

        foreach (array_values($input) as $row) {
            $cleaned[] = $this->cleanRow($kind, $row);
        }

        // Explicit keys first so derived keys never collide with them.
        foreach ($cleaned as $row) {
            $explicit = $row[SafeCopyAllowlist::keyFieldFor($kind)] ?? null;
            if ($explicit === null) {
                continue;
            }
            if (isset($seen[$explicit])) {
                throw SeasonalTemplatesDomainException::snapshotInvalid('مفتاح مكرر في اللقطة: '.mb_substr($explicit, 0, 64));
            }
            $seen[$explicit] = true;
        }

        foreach ($cleaned as $row) {
            $key = SafeCopyAllowlist::keyFieldFor($kind);
            $code = $row[$key] ?? $this->deriveCode($kind, $row, $seen);

            if (mb_strlen($code) > CopyKeys::MAX_LENGTH) {
                throw SeasonalTemplatesDomainException::snapshotInvalid('مفتاح النسخ أطول من الحد المسموح.');
            }

            $row[$key] = $code;
            $rows[$code] = $this->complete($kind, $row);
        }

        ksort($rows);

        return ['anchor' => $anchor, 'rows' => $rows];
    }

    private function anchor(mixed $anchor): ?string
    {
        if ($anchor === null || $anchor === '') {
            return null;
        }

        $parsed = is_string($anchor) ? Carbon::createFromFormat('!Y-m-d', $anchor) : false;
        if ($parsed === false || $parsed->toDateString() !== $anchor) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('تاريخ مرجع اللقطة غير صالح.');
        }

        return $anchor;
    }

    /**
     * @return array<string, mixed> validated, trimmed fields actually present in the input
     */
    private function cleanRow(string $kind, mixed $row): array
    {
        if (! is_array($row)) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('صيغة صف اللقطة غير صالحة.');
        }

        $denied = array_diff(array_keys($row), SafeCopyAllowlist::fieldsFor($kind));
        if ($denied !== []) {
            throw SeasonalTemplatesDomainException::snapshotInvalid(
                'حقل غير مصرح بنسخه في اللقطة: '.mb_substr((string) reset($denied), 0, 64),
            );
        }

        foreach ($row as $value) {
            if (is_array($value) || is_object($value)) {
                throw SeasonalTemplatesDomainException::snapshotInvalid('قيم اللقطة يجب أن تكون قيماً بسيطة.');
            }
        }

        try {
            $validated = Validator::make($row, $this->rules($kind))->validate();
        } catch (Throwable $e) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('قيم اللقطة غير صالحة لنوع البيانات: '.mb_substr($kind, 0, 64));
        }

        $clean = [];
        foreach ($validated as $field => $value) {
            $clean[$field] = is_string($value) ? trim($value) : $value;
            if ($clean[$field] === '') {
                $clean[$field] = null;
            }
        }

        return $clean;
    }

    /**
     * Fill every allowlisted field (null when absent), apply stable defaults and casts.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function complete(string $kind, array $row): array
    {
        $fields = [];
        foreach (SafeCopyAllowlist::fieldsFor($kind) as $field) {
            $fields[$field] = $row[$field] ?? null;
        }

        foreach (['is_active'] as $bool) {
            if (array_key_exists($bool, $fields)) {
                $fields[$bool] = (bool) $fields[$bool];
            }
        }
        foreach (['operational_capacity', 'sort_order', 'contract_category_id', 'organization_unit_id', 'offset_days'] as $int) {
            if (array_key_exists($int, $fields) && $fields[$int] !== null) {
                $fields[$int] = (int) $fields[$int];
            }
        }

        if ($kind === SafeCopyAllowlist::CONTRACT_DEFINITIONS) {
            $fields['counterparty_kind'] ??= 'organization';
            $fields['currency'] = strtoupper((string) ($fields['currency'] ?? config('contracts.default_currency', 'SAR')));
        }

        if ($kind === SafeCopyAllowlist::TASK_DEFINITIONS) {
            $fields['priority'] ??= 'medium';
        }

        if ($kind === SafeCopyAllowlist::SEASON_REMINDER_RULES) {
            // Recipients are always permission holders (S08-D14); the permission must be allowlisted now.
            $fields['recipient_mode'] = SeasonReminderRule::RECIPIENT_MODE_PERMISSION_HOLDERS;
            SeasonReminderRecipientPermissions::assertApproved((string) $fields['recipient_permission']);
            SeasonReminderRuleGuard::normalizeTrigger($fields);
        }

        ksort($fields);

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seen
     */
    private function deriveCode(string $kind, array $row, array &$seen): string
    {
        $title = (string) ($row['title'] ?? '');

        return match ($kind) {
            SafeCopyAllowlist::CONTRACT_DEFINITIONS, SafeCopyAllowlist::SEASON_PROJECTS => CopyKeys::unique($title, $seen),
            SafeCopyAllowlist::TASK_DEFINITIONS => CopyKeys::unique(CopyKeys::task((string) $row['project_code'], $title), $seen),
            SafeCopyAllowlist::SEASON_PROJECT_PHASES => $this->phaseCode($row, $seen),
            default => throw SeasonalTemplatesDomainException::snapshotInvalid('مفتاح الصف مطلوب.'),
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, true>  $seen
     */
    private function phaseCode(array $row, array &$seen): string
    {
        $code = CopyKeys::phase((string) $row['project_code'], (int) $row['sort_order']);
        if (isset($seen[$code])) {
            throw SeasonalTemplatesDomainException::snapshotInvalid('ترتيب مرحلة مكرر داخل المشروع نفسه.');
        }
        $seen[$code] = true;

        return $code;
    }

    /**
     * @return array<string, list<string>>
     */
    private function rules(string $kind): array
    {
        $date = ['nullable', 'date_format:Y-m-d'];
        $text = ['nullable', 'string', 'max:5000'];

        return match ($kind) {
            SafeCopyAllowlist::SEASON_PROGRAMS => [
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:255'],
                'description' => $text,
                'is_active' => ['required', 'boolean'],
            ],
            SafeCopyAllowlist::SEASON_PILGRIM_GROUPS => [
                'code' => ['required', 'string', 'max:40'],
                'name' => ['required', 'string', 'max:255'],
                'notes' => $text,
            ],
            SafeCopyAllowlist::TRANSPORT_ENTITLEMENTS => [
                'program_code' => ['required', 'string', 'max:40'],
                'vehicle_class' => ['nullable', 'in:bus,minibus,car'],
                'service_level' => ['nullable', 'in:standard,premium'],
                'notes' => $text,
            ],
            SafeCopyAllowlist::TRANSPORT_SEASON_VEHICLES => [
                'plate_number' => ['required', 'string', 'max:32'],
                'operational_capacity' => ['required', 'integer', 'min:1', 'max:65535'],
                'service_level' => ['required', 'in:standard,premium'],
                'is_active' => ['required', 'boolean'],
                'notes' => $text,
            ],
            SafeCopyAllowlist::ACCOMMODATION_SEASON_FACILITIES => [
                'facility_code' => ['required', 'string', 'max:64'],
                'audience' => ['nullable', 'in:male,female,family'],
                'is_active' => ['required', 'boolean'],
            ],
            SafeCopyAllowlist::CONTRACT_DEFINITIONS => [
                'code' => ['sometimes', 'nullable', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'contract_category_id' => ['required', 'integer', 'min:1'],
                'counterparty_kind' => ['nullable', 'in:person,organization,other'],
                'notes' => $text,
                'currency' => ['nullable', 'string', 'size:3', 'alpha'],
                'start_date' => ['required', 'date_format:Y-m-d'],
                'end_date' => $date,
            ],
            SafeCopyAllowlist::SEASON_PROJECTS => [
                'code' => ['sometimes', 'nullable', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'description' => $text,
            ],
            SafeCopyAllowlist::SEASON_PROJECT_PHASES => [
                'code' => ['sometimes', 'nullable', 'string', 'max:255'],
                'project_code' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'sort_order' => ['required', 'integer', 'min:0', 'max:65535'],
                'starts_on' => $date,
                'ends_on' => $date,
            ],
            SafeCopyAllowlist::TASK_DEFINITIONS => [
                'code' => ['sometimes', 'nullable', 'string', 'max:255'],
                'project_code' => ['required', 'string', 'max:255'],
                'title' => ['required', 'string', 'max:255'],
                'description' => $text,
                'notes' => $text,
                'priority' => ['nullable', 'in:low,medium,high'],
                'start_date' => $date,
                'due_date' => $date,
                'organization_unit_id' => ['nullable', 'integer', 'min:1'],
            ],
            SafeCopyAllowlist::SEASON_REMINDER_RULES => [
                'code' => ['required', 'string', 'max:64'],
                'title' => ['required', 'string', 'max:255'],
                'body' => ['nullable', 'string', 'max:1000'],
                'severity' => ['required', 'in:info,warning,critical'],
                'trigger_mode' => ['required', 'in:absolute_date,offset_from_season_start'],
                'trigger_on' => $date,
                'offset_days' => ['nullable', 'integer', 'between:-'.SeasonReminderRuleGuard::MAX_OFFSET_DAYS.','.SeasonReminderRuleGuard::MAX_OFFSET_DAYS],
                'recipient_mode' => ['nullable', 'in:permission_holders'],
                'recipient_permission' => ['required', 'string', 'max:64'],
            ],
            default => throw SeasonalTemplatesDomainException::kindDenied($kind),
        };
    }
}
