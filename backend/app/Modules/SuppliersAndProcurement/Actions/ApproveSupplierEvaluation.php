<?php

namespace App\Modules\SuppliersAndProcurement\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationDispatcher;
use App\Modules\Notifications\Support\NotificationRecipientResolver;
use App\Modules\SuppliersAndProcurement\Enums\EvaluationStatus;
use App\Modules\SuppliersAndProcurement\Exceptions\SupplierDomainException;
use App\Modules\SuppliersAndProcurement\Models\SupplierEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ApproveSupplierEvaluation
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly AuthorizationSecurity $security,
        private readonly NotificationDispatcher $notifications,
        private readonly NotificationRecipientResolver $recipients,
    ) {}

    /**
     * @param  array{monitoring_recommended?: bool}  $data
     */
    public function execute(
        User $actor,
        SupplierEvaluation $evaluation,
        array $data,
        Request $request,
    ): SupplierEvaluation {
        $tenant = $this->tenantContext->require();

        $approved = DB::transaction(function () use ($actor, $evaluation, $data, $request, $tenant): SupplierEvaluation {
            /** @var SupplierEvaluation $locked */
            $locked = SupplierEvaluation::query()->whereKey($evaluation->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPendingApproval()) {
                throw SupplierDomainException::evaluationInvalidStatus();
            }

            if ((int) $locked->prepared_by === (int) $actor->id) {
                $this->security->record(AuthorizationSecurityEvent::EVALUATION_SOD_VIOLATION, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'entity_type' => 'supplier_evaluation',
                    'entity_id' => $locked->id,
                    'action' => 'approve',
                ], $request);
                throw SupplierDomainException::evaluationSodViolation();
            }

            $finalMonitoring = array_key_exists('monitoring_recommended', $data)
                ? (bool) $data['monitoring_recommended']
                : (bool) $locked->monitoring_recommended;

            $locked->forceFill([
                'status' => EvaluationStatus::Approved,
                'approved_by' => $actor->id,
                'approved_at' => now(),
                'monitoring_recommended' => $finalMonitoring,
                'monitoring_set_by' => $actor->id,
                'monitoring_set_at' => now(),
            ]);
            $locked->save();

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_APPROVED, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $locked->id,
                'evaluation_number' => $locked->evaluation_number,
                'total_score' => (string) $locked->total_score,
                'monitoring_recommended' => $finalMonitoring,
            ], $request);

            $this->security->record(AuthorizationSecurityEvent::EVALUATION_MONITORING_SET, [
                'tenant_id' => $tenant->id,
                'actor_id' => $actor->id,
                'entity_type' => 'supplier_evaluation',
                'entity_id' => $locked->id,
                'monitoring_recommended' => $finalMonitoring,
                'final' => true,
            ], $request);

            return $locked;
        });

        if ($approved->monitoring_recommended) {
            $this->emitMonitoringNotification($approved, $actor);
        }

        return $approved->fresh([
            'scoreSnapshots',
            'preparer',
            'approver',
            'category',
            'supplier',
            'season',
            'criteriaPack',
        ]) ?? $approved;
    }

    private function emitMonitoringNotification(SupplierEvaluation $evaluation, User $actor): void
    {
        $users = $this->recipients->usersWithPermission('supplier_evaluations.approve');
        $users = array_values(array_filter(
            $users,
            static fn (User $user): bool => (int) $user->id !== (int) $actor->id,
        ));

        $evaluation->loadMissing('supplier');

        $this->notifications->notify(
            type: NotificationType::SupplierEvaluationMonitoringRecommended,
            recipientUsers: $users,
            entityType: 'supplier_evaluation',
            entityId: (int) $evaluation->id,
            dedupeBucket: 'monitoring',
            context: [
                'evaluation_id' => $evaluation->id,
                'evaluation_number' => $evaluation->evaluation_number,
                'supplier_id' => $evaluation->supplier_id,
                'supplier_number' => $evaluation->supplier?->supplier_number,
                'total_score' => (string) $evaluation->total_score,
            ],
        );
    }
}
