<?php

namespace App\Core\Audit;

/**
 * Maps semantic event context payloads into audit_logs column values.
 */
final class AuditEventMapper
{
    /**
     * @var list<string>
     */
    private const COLUMN_KEYS = [
        'correlation_id',
        'ip',
        'user_agent',
        'tenant_id',
        'actor_id',
        'user_id',
        'reason',
        'context_type',
        'from_status',
        'to_status',
        'source_balance_before',
        'source_balance_after',
        'destination_balance_before',
        'destination_balance_after',
        'balance_before',
        'balance_after',
    ];

    /**
     * Prefer first matching entity descriptor in context.
     *
     * @var list<array{type: string, id: string, number: string|null, label: string|null}>
     */
    private const ENTITY_HINTS = [
        ['type' => 'contract', 'id' => 'contract_id', 'number' => 'contract_number', 'label' => 'contract_title'],
        ['type' => 'meeting', 'id' => 'meeting_id', 'number' => 'meeting_number', 'label' => 'meeting_title'],
        ['type' => 'decision', 'id' => 'decision_id', 'number' => 'decision_number', 'label' => 'decision_title'],
        ['type' => 'task', 'id' => 'task_id', 'number' => 'task_number', 'label' => 'task_title'],
        ['type' => 'document', 'id' => 'document_id', 'number' => 'document_number', 'label' => 'title'],
        ['type' => 'warehouse', 'id' => 'warehouse_id', 'number' => 'warehouse_number', 'label' => 'warehouse_name'],
        ['type' => 'inventory_item', 'id' => 'inventory_item_id', 'number' => 'item_number', 'label' => 'item_name'],
        ['type' => 'asset', 'id' => 'asset_id', 'number' => 'asset_number', 'label' => 'asset_name'],
        ['type' => 'custody', 'id' => 'custody_id', 'number' => 'custody_number', 'label' => null],
        ['type' => 'employee', 'id' => 'employee_id', 'number' => 'employee_number', 'label' => 'employee_name'],
        ['type' => 'organization_unit', 'id' => 'organization_unit_id', 'number' => 'organization_unit_code', 'label' => 'organization_unit_name'],
        ['type' => 'role', 'id' => 'role_id', 'number' => 'role_code', 'label' => 'role_name'],
        ['type' => 'user', 'id' => 'target_user_id', 'number' => null, 'label' => 'target_user_name'],
        ['type' => 'position', 'id' => 'position_id', 'number' => 'position_code', 'label' => 'position_name'],
        ['type' => 'contract_category', 'id' => 'contract_category_id', 'number' => null, 'label' => 'category_name'],
        ['type' => 'document_category', 'id' => 'document_category_id', 'number' => null, 'label' => 'category_name'],
        ['type' => 'inventory_category', 'id' => 'inventory_category_id', 'number' => null, 'label' => 'category_name'],
        ['type' => 'asset_category', 'id' => 'asset_category_id', 'number' => null, 'label' => 'category_name'],
    ];

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *   entity_type: ?string,
     *   entity_id: ?int,
     *   entity_number: ?string,
     *   entity_label: ?string,
     *   reason: ?string,
     *   before_values: ?array<string, mixed>,
     *   after_values: ?array<string, mixed>,
     *   metadata: array<string, mixed>
     * }
     */
    public function map(string $eventType, array $context): array
    {
        $entity = $this->resolveEntity($eventType, $context);
        $before = null;
        $after = null;

        if (isset($context['from_status']) || isset($context['to_status'])) {
            if (isset($context['from_status'])) {
                $before = ['status' => $context['from_status']];
            }
            if (isset($context['to_status'])) {
                $after = ['status' => $context['to_status']];
            }
        }

        if (isset($context['before_values']) && is_array($context['before_values'])) {
            /** @var array<string, mixed> $beforeValues */
            $beforeValues = $context['before_values'];
            $before = $beforeValues;
        }
        if (isset($context['after_values']) && is_array($context['after_values'])) {
            /** @var array<string, mixed> $afterValues */
            $afterValues = $context['after_values'];
            $after = $afterValues;
        }

        $metadata = $context;
        foreach (array_merge(self::COLUMN_KEYS, $this->entityContextKeys()) as $key) {
            unset($metadata[$key]);
        }
        unset($metadata['before_values'], $metadata['after_values'], $metadata['metadata']);

        if (isset($context['metadata']) && is_array($context['metadata'])) {
            /** @var array<string, mixed> $extra */
            $extra = $context['metadata'];
            $metadata = array_merge($metadata, $extra);
        }

        // Keep useful transition / inventory numbers in metadata when not already columns.
        foreach ([
            'from_status', 'to_status', 'mime_type', 'size_bytes',
            'source_warehouse_id', 'destination_warehouse_id', 'quantity',
            'transfer_group_id', 'employee_id', 'custody_id', 'custody_number',
            'linkable_type', 'linkable_id',
        ] as $keep) {
            if (array_key_exists($keep, $context) && ! array_key_exists($keep, $metadata)) {
                $metadata[$keep] = $context[$keep];
            }
        }

        $reason = isset($context['reason']) && is_string($context['reason'])
            ? mb_substr($context['reason'], 0, 500)
            : null;

        return [
            'entity_type' => $entity['type'],
            'entity_id' => $entity['id'],
            'entity_number' => $entity['number'],
            'entity_label' => $entity['label'],
            'reason' => $reason,
            'before_values' => $before,
            'after_values' => $after,
            'metadata' => $metadata,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{type: ?string, id: ?int, number: ?string, label: ?string}
     */
    private function resolveEntity(string $eventType, array $context): array
    {
        if (isset($context['entity_type']) && is_string($context['entity_type'])) {
            return [
                'type' => $context['entity_type'],
                'id' => isset($context['entity_id']) ? (int) $context['entity_id'] : null,
                'number' => isset($context['entity_number']) ? (string) $context['entity_number'] : null,
                'label' => isset($context['entity_label']) ? (string) $context['entity_label'] : null,
            ];
        }

        foreach (self::ENTITY_HINTS as $hint) {
            if (! isset($context[$hint['id']])) {
                continue;
            }

            $label = null;
            if ($hint['label'] !== null && isset($context[$hint['label']])) {
                $label = (string) $context[$hint['label']];
            } elseif (isset($context['title'])) {
                $label = (string) $context['title'];
            } elseif (isset($context['name'])) {
                $label = (string) $context['name'];
            }

            $number = null;
            if ($hint['number'] !== null && isset($context[$hint['number']])) {
                $number = (string) $context[$hint['number']];
            }

            return [
                'type' => $hint['type'],
                'id' => (int) $context[$hint['id']],
                'number' => $number,
                'label' => $label !== null ? mb_substr($label, 0, 255) : null,
            ];
        }

        // USER_* events often target user_id as the subject.
        if (str_starts_with($eventType, 'USER_') && isset($context['user_id'])) {
            return [
                'type' => 'user',
                'id' => (int) $context['user_id'],
                'number' => null,
                'label' => isset($context['user_name']) ? (string) $context['user_name'] : null,
            ];
        }

        return [
            'type' => null,
            'id' => null,
            'number' => null,
            'label' => null,
        ];
    }

    /**
     * @return list<string>
     */
    private function entityContextKeys(): array
    {
        $keys = ['entity_type', 'entity_id', 'entity_number', 'entity_label', 'title', 'name'];
        foreach (self::ENTITY_HINTS as $hint) {
            $keys[] = $hint['id'];
            if ($hint['number'] !== null) {
                $keys[] = $hint['number'];
            }
            if ($hint['label'] !== null) {
                $keys[] = $hint['label'];
            }
        }

        return array_values(array_unique($keys));
    }
}
