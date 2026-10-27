<?php

use App\Modules\Audit\Actions\ListAuditLogs;
use App\Modules\Audit\Models\AuditLog;

it('does not retrieve detail blobs for audit lists but keeps detail responses intact', function (): void {
    $actor = actingAsTenantOwner();
    $log = AuditLog::record([
        'tenant_id' => $actor->tenant_id, 'context_type' => 'tenant', 'actor_type' => 'user',
        'actor_user_id' => $actor->id, 'actor_label' => 'Synthetic actor',
        'event_type' => 'DOCUMENT_DOWNLOADED', 'entity_type' => 'document', 'entity_id' => 7,
        'entity_label' => 'prefix-middle-suffix', 'metadata' => ['large' => str_repeat('x', 16384)],
        'before_values' => ['old' => 1], 'after_values' => ['new' => 2],
        'correlation_id' => 'performance-audit', 'source' => 'http', 'created_at' => now(),
    ]);
    $page = withTenant($actor->tenant, fn () => app(ListAuditLogs::class)->execute(['search' => 'middle']));
    expect($page->total())->toBe(1);
    $summary = $page->items()[0]->getAttributes();
    expect($summary)->not->toHaveKeys(['metadata', 'before_values', 'after_values', 'ip_address', 'user_agent']);
    spaGetJson('/api/v1/audit-logs?search=middle')->assertOk()->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.id', $log->id)->assertJsonMissingPath('data.0.metadata');
    spaGetJson('/api/v1/audit-logs/'.$log->id)->assertOk()
        ->assertJsonPath('data.metadata.large', str_repeat('x', 2000)) // Existing sanitizer's string limit.
        ->assertJsonPath('data.before_values.old', 1)->assertJsonPath('data.after_values.new', 2);
});
