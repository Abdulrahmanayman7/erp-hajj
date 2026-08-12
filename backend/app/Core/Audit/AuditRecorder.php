<?php

namespace App\Core\Audit;

use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\PlatformContext;
use App\Core\Tenancy\TenantContext;
use App\Models\User;
use App\Modules\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Central append-only audit persistence (ADR-0015).
 */
class AuditRecorder
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly PlatformContext $platformContext,
        private readonly CorrelationId $correlationId,
        private readonly SensitiveFieldSanitizer $sanitizer,
        private readonly AuditEventMapper $mapper,
    ) {}

    /**
     * Persist a domain / authorization security event (fail-closed — exceptions propagate).
     *
     * @param  array<string, mixed>  $context
     */
    public function recordAuthorizationEvent(string $eventType, array $context = []): AuditLog
    {
        return $this->persist($eventType, $context, failClosed: true);
    }

    /**
     * Persist an auth security event. Failures are logged critically and swallowed (ADR-0015).
     *
     * @param  array<string, mixed>  $context
     */
    public function recordAuthEvent(string $eventType, array $context = []): ?AuditLog
    {
        try {
            return $this->persist($eventType, $context, failClosed: false);
        } catch (Throwable $e) {
            Log::critical('audit.auth_persist_failed', [
                'event' => $eventType,
                'message' => $e->getMessage(),
                'exception' => $e::class,
            ]);

            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function persist(string $eventType, array $context, bool $failClosed): AuditLog
    {
        $mapped = $this->mapper->map($eventType, $context);

        $tenantId = $this->resolveTenantId($context);
        $contextType = $this->resolveContextType($context, $tenantId);
        [$actorType, $actorUserId, $actorLabel] = $this->resolveActor($context, $contextType);

        $correlation = isset($context['correlation_id']) && is_string($context['correlation_id']) && $context['correlation_id'] !== ''
            ? $context['correlation_id']
            : ($this->correlationId->get() ?? (string) Str::uuid());

        $ip = isset($context['ip']) && is_string($context['ip'])
            ? mb_substr($context['ip'], 0, 45)
            : null;
        $ua = isset($context['user_agent']) && is_string($context['user_agent'])
            ? mb_substr($context['user_agent'], 0, 512)
            : null;

        $source = $this->resolveSource();

        $attributes = [
            'tenant_id' => $tenantId,
            'context_type' => $contextType,
            'actor_type' => $actorType,
            'actor_user_id' => $actorUserId,
            'actor_label' => $actorLabel,
            'event_type' => $eventType,
            'entity_type' => $mapped['entity_type'],
            'entity_id' => $mapped['entity_id'],
            'entity_number' => $mapped['entity_number'],
            'entity_label' => $mapped['entity_label'],
            'reason' => $mapped['reason'],
            'metadata' => $this->sanitizer->sanitize($mapped['metadata'] !== [] ? $mapped['metadata'] : null),
            'before_values' => $this->sanitizer->sanitize($mapped['before_values']),
            'after_values' => $this->sanitizer->sanitize($mapped['after_values']),
            'ip_address' => $ip,
            'user_agent' => $ua,
            'correlation_id' => mb_substr($correlation, 0, 64),
            'source' => $source,
            'created_at' => now(),
        ];

        try {
            return AuditLog::record($attributes);
        } catch (Throwable $e) {
            if ($failClosed) {
                throw $e;
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveTenantId(array $context): ?int
    {
        if ($this->tenantContext->has()) {
            return $this->tenantContext->id();
        }

        if (isset($context['tenant_id']) && $context['tenant_id'] !== null && $context['tenant_id'] !== '') {
            return (int) $context['tenant_id'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveContextType(array $context, ?int $tenantId): string
    {
        if (isset($context['context_type']) && in_array($context['context_type'], ['tenant', 'platform'], true)) {
            return (string) $context['context_type'];
        }

        if ($this->platformContext->isActive() && $tenantId === null) {
            return 'platform';
        }

        if ($this->platformContext->isActive()) {
            return 'platform';
        }

        return $tenantId !== null ? 'tenant' : 'platform';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{0: string, 1: ?int, 2: ?string}
     */
    private function resolveActor(array $context, string $contextType): array
    {
        if (isset($context['actor_type']) && in_array($context['actor_type'], ['user', 'system', 'platform'], true)) {
            $type = (string) $context['actor_type'];
            $id = isset($context['actor_id']) ? (int) $context['actor_id'] : (isset($context['user_id']) ? (int) $context['user_id'] : null);
            $label = isset($context['actor_label']) && is_string($context['actor_label'])
                ? mb_substr($context['actor_label'], 0, 255)
                : ($type === 'system' ? 'النظام' : $this->labelForUserId($id));

            return [$type, $id, $label];
        }

        if ($this->runningInConsoleWithoutHttpUser()) {
            return ['system', null, 'النظام'];
        }

        /** @var User|null $authUser */
        $authUser = Auth::user();

        if ($authUser !== null) {
            $type = $contextType === 'platform' || $authUser->isPlatformUser() ? 'platform' : 'user';
            if (isset($context['actor_id'])) {
                $id = (int) $context['actor_id'];
                $label = $this->labelForUserId($id) ?? $this->labelForUser($authUser);

                return [$type === 'platform' && ! $authUser->isPlatformUser() ? 'user' : ($authUser->isPlatformUser() ? 'platform' : 'user'), $id, $label];
            }

            return [
                $authUser->isPlatformUser() ? 'platform' : 'user',
                $authUser->id,
                $this->labelForUser($authUser),
            ];
        }

        if (isset($context['actor_id'])) {
            $id = (int) $context['actor_id'];

            return ['user', $id, $this->labelForUserId($id)];
        }

        if (isset($context['user_id'])) {
            $id = (int) $context['user_id'];

            return ['user', $id, $this->labelForUserId($id)];
        }

        return ['system', null, 'النظام'];
    }

    private function runningInConsoleWithoutHttpUser(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        return Auth::user() === null && ! request()->hasSession();
    }

    private function resolveSource(): string
    {
        if (app()->runningInConsole()) {
            return 'console';
        }

        return 'http';
    }

    private function labelForUser(User $user): string
    {
        $name = trim((string) $user->name);
        $email = trim((string) $user->email);

        if ($name !== '' && $email !== '') {
            return mb_substr($name.' <'.$email.'>', 0, 255);
        }

        return mb_substr($name !== '' ? $name : $email, 0, 255);
    }

    private function labelForUserId(?int $id): ?string
    {
        if ($id === null) {
            return null;
        }

        $user = User::query()->find($id);

        return $user !== null ? $this->labelForUser($user) : null;
    }
}
