<?php

namespace App\Core\Authorization;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Modules\Authorization\Models\Permission;
use Illuminate\Support\Facades\DB;

/**
 * Idempotent upsert of the global permission catalog for implemented modules.
 */
final class PermissionCatalogSynchronizer
{
    public function __construct(private readonly AuthorizationSecurity $security) {}

    /**
     * @return array{created: int, updated: int, total: int}
     */
    public function sync(): array
    {
        $created = 0;
        $updated = 0;

        $definitions = PermissionCatalog::allDefinitions();

        DB::transaction(function () use ($definitions, &$created, &$updated): void {
            foreach ($definitions as $definition) {
                $existing = Permission::query()->where('name', $definition['name'])->first();

                if ($existing === null) {
                    Permission::query()->create($definition);
                    $created++;

                    continue;
                }

                $dirty = false;
                foreach (['display_name', 'module', 'description'] as $field) {
                    if ($existing->{$field} !== $definition[$field]) {
                        $existing->{$field} = $definition[$field];
                        $dirty = true;
                    }
                }

                if ($dirty) {
                    $existing->save();
                    $updated++;
                }
            }
        });

        $result = [
            'created' => $created,
            'updated' => $updated,
            'total' => count($definitions),
        ];

        $this->security->record(AuthorizationSecurityEvent::PERMISSION_CATALOG_SYNCED, [
            'created' => $created,
            'updated' => $updated,
            'total' => $result['total'],
        ]);

        return $result;
    }
}
