<?php

namespace App\Modules\SeasonPilgrims\Actions\Pilgrims;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\SeasonPilgrims\Models\Pilgrim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Corrects current identity (F19). Seasonal snapshots are NOT touched.
 */
final class UpdatePilgrim
{
    private const FIELDS = ['full_name', 'gender', 'nationality', 'phone', 'notes'];

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, Pilgrim $pilgrim, array $data, Request $request): Pilgrim
    {
        $tenant = $this->tenantContext->require();

        return DB::transaction(function () use ($actor, $pilgrim, $data, $request, $tenant): Pilgrim {
            /** @var Pilgrim $locked */
            $locked = Pilgrim::query()->whereKey($pilgrim->id)->lockForUpdate()->firstOrFail();

            $changes = array_intersect_key($data, array_flip(self::FIELDS));
            if (isset($changes['full_name'])) {
                $changes['full_name'] = trim((string) $changes['full_name']);
            }
            if (isset($changes['nationality'])) {
                $changes['nationality'] = mb_strtoupper((string) $changes['nationality']);
            }

            $before = $this->values($locked);
            $locked->update($changes);
            $after = $this->values($locked);

            $this->security->record(AuthorizationSecurityEvent::PILGRIM_UPDATED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'pilgrim_id' => $locked->id,
                'pilgrim_name' => $locked->full_name,
                'before_values' => array_diff_assoc($before, $after),
                'after_values' => array_diff_assoc($after, $before),
            ], $request);

            return $locked->load('identityDocuments');
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function values(Pilgrim $pilgrim): array
    {
        return [
            'full_name' => $pilgrim->full_name,
            'gender' => $pilgrim->gender?->value,
            'nationality' => $pilgrim->nationality,
            'phone' => $pilgrim->phone,
            'notes' => $pilgrim->notes,
        ];
    }
}
