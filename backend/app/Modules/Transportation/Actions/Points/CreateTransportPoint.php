<?php

namespace App\Modules\Transportation\Actions\Points;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Models\User;
use App\Modules\Transportation\Exceptions\TransportationDomainException;
use App\Modules\Transportation\Models\TransportPoint;
use App\Modules\Transportation\Support\TransportAudit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CreateTransportPoint
{
    public function __construct(private readonly TransportAudit $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function execute(User $actor, array $data, Request $request): TransportPoint
    {
        return DB::transaction(function () use ($actor, $data, $request): TransportPoint {
            $code = trim((string) $data['code']);

            if (TransportPoint::query()->where('code', $code)->exists()) {
                throw TransportationDomainException::pointCodeTaken();
            }

            try {
                $point = TransportPoint::query()->create([
                    'code' => $code,
                    'name' => trim((string) $data['name']),
                    'point_type' => $data['point_type'],
                    'notes' => $data['notes'] ?? null,
                    'is_active' => true,
                    'created_by' => $actor->id,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw TransportationDomainException::pointCodeTaken();
            }

            $this->audit->record(AuthorizationSecurityEvent::TRANSPORT_POINT_CREATED, $actor->id, 'transport_point', $point->id, $point->code, $point->name, [
                'after_values' => ['code' => $point->code, 'name' => $point->name, 'point_type' => $point->point_type->value],
            ], $request);

            return $point;
        });
    }
}
