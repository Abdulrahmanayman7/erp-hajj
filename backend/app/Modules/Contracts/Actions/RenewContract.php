<?php

namespace App\Modules\Contracts\Actions;

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\Support\AuthorizationSecurity;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Contracts\Enums\ContractStatus;
use App\Modules\Contracts\Exceptions\ContractDomainException;
use App\Modules\Contracts\Models\Contract;
use App\Modules\Contracts\Support\ContractLifecycle;
use App\Modules\Contracts\Support\ContractNumberGenerator;
use App\Modules\Contracts\Support\ContractTransitionRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class RenewContract
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly ContractNumberGenerator $numbers,
        private readonly ContractTransitionRecorder $transitions,
        private readonly AuthorizationSecurity $security,
    ) {}

    /**
     * @return array{source: Contract, successor: Contract}
     */
    public function execute(User $actor, Contract $contract, ?string $comment, Request $request): array
    {
        $tenant = $this->tenantContext->require();

        try {
            return DB::transaction(function () use ($actor, $contract, $comment, $request, $tenant): array {
                $source = Contract::query()->whereKey($contract->id)->lockForUpdate()->firstOrFail();

                // Prefer ALREADY_RENEWED over INVALID_STATUS_TRANSITION when the source
                // was renewed (status=renewed and/or a successor child already exists).
                if (
                    $source->status === ContractStatus::Renewed
                    || Contract::query()->where('renewed_from_contract_id', $source->id)->exists()
                ) {
                    throw ContractDomainException::alreadyRenewed();
                }

                if ($source->status !== ContractStatus::Executing) {
                    throw ContractDomainException::invalidStatusTransition();
                }

                ContractLifecycle::assertAllowed(ContractStatus::Executing, ContractStatus::Renewed);

                // Dates are not copied from source. start_date is NOT NULL in schema, so seed with
                // tenant "today" and open-ended end_date; operator must set the real period before leaving draft.
                $today = now($tenant->timezone ?? config('app.timezone', 'Asia/Riyadh'))->toDateString();

                $successor = new Contract([
                    'title' => $source->title,
                    'contract_category_id' => $source->contract_category_id,
                    'status' => ContractStatus::Draft,
                    'counterparty_name' => $source->counterparty_name,
                    'counterparty_kind' => $source->counterparty_kind,
                    'employee_id' => $source->employee_id,
                    'organization_unit_id' => $source->organization_unit_id,
                    'start_date' => $today,
                    'end_date' => null,
                    'value' => $source->value,
                    'currency' => $source->currency,
                    'notes' => $source->notes,
                    'created_by' => $actor->id,
                    'renewed_from_contract_id' => $source->id,
                ]);
                $successor->contract_number = $this->numbers->next();
                $successor->save();

                $source->status = ContractStatus::Renewed;
                $source->save();

                $this->transitions->record(
                    $source,
                    ContractStatus::Executing,
                    ContractStatus::Renewed,
                    $actor,
                    $comment !== null ? trim($comment) : null,
                    $request,
                );
                $this->transitions->record($successor, null, ContractStatus::Draft, $actor, null, $request);

                $this->security->record(AuthorizationSecurityEvent::CONTRACT_RENEWED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'contract_id' => $source->id,
                    'contract_number' => $source->contract_number,
                    'successor_contract_id' => $successor->id,
                    'successor_contract_number' => $successor->contract_number,
                ], $request);

                $this->security->record(AuthorizationSecurityEvent::CONTRACT_CREATED, [
                    'tenant_id' => $tenant->id,
                    'actor_id' => $actor->id,
                    'contract_id' => $successor->id,
                    'contract_number' => $successor->contract_number,
                    'status' => ContractStatus::Draft->value,
                    'renewed_from_contract_id' => $source->id,
                ], $request);

                return [
                    'source' => $source->fresh()->load([
                        'category', 'employee', 'organizationUnit', 'createdBy', 'renewalChild', 'statusTransitions.actor',
                    ]),
                    'successor' => $successor->load([
                        'category', 'employee', 'organizationUnit', 'createdBy', 'renewedFrom', 'statusTransitions.actor',
                    ]),
                ];
            });
        } catch (QueryException $e) {
            if ($this->isRenewedFromUniqueViolation($e)) {
                throw ContractDomainException::alreadyRenewed();
            }

            throw $e;
        }
    }

    private function isRenewedFromUniqueViolation(QueryException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'contracts_renewed_from_unique')
            || (str_contains($message, 'renewed_from_contract_id') && (
                str_contains($message, 'UNIQUE') || str_contains($message, 'Duplicate')
            ));
    }
}
