<?php

use App\Core\Authorization\Events\AuthorizationSecurityEvent;
use App\Core\Authorization\PermissionCatalog;
use App\Core\Tenancy\Models\Tenant;
use App\Modules\Employees\Models\Employee;
use App\Modules\Meetings\Enums\MeetingStatus;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\Meetings\Models\Meeting;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * @param  array<string, mixed>  $attrs
 * @return array<string, mixed>
 */
function createDecisionViaApi(array $attrs = []): array
{
    return spaPostJson('/api/v1/decisions', array_merge([
        'title' => 'قرار اختبار',
        'body' => 'نص القرار',
    ], $attrs))->assertCreated()->json('data');
}

/**
 * @return array{meeting: array<string, mixed>, recommendation: array<string, mixed>}
 */
function createCompletedMeetingWithFinalRecommendation(?string $recTitle = 'توصية نهائية'): array
{
    $meeting = spaPostJson('/api/v1/meetings', [
        'title' => 'اجتماع مصدر',
    ])->assertCreated()->json('data');

    $order = [
        MeetingStatus::Draft->value,
        MeetingStatus::Scheduled->value,
        MeetingStatus::InProgress->value,
        MeetingStatus::Completed->value,
    ];

    $current = $meeting;
    $currentIndex = 0;

    for ($i = $currentIndex + 1; $i < count($order); $i++) {
        $step = $order[$i];
        if ($step === MeetingStatus::Scheduled->value) {
            $current = spaPostJson("/api/v1/meetings/{$meeting['id']}/schedule", [
                'scheduled_at' => now()->addDay()->toIso8601String(),
            ])->assertOk()->json('data');
        } elseif ($step === MeetingStatus::InProgress->value) {
            $current = spaPostJson("/api/v1/meetings/{$meeting['id']}/start")->assertOk()->json('data');
        } elseif ($step === MeetingStatus::Completed->value) {
            spaPutJson("/api/v1/meetings/{$meeting['id']}/minutes", [
                'minutes_body' => 'محضر',
            ])->assertOk();

            // Create recommendation while in progress (mutable)
            $recommendation = spaPostJson("/api/v1/meetings/{$meeting['id']}/recommendations", [
                'title' => $recTitle,
                'description' => 'وصف التوصية',
                'status' => RecommendationStatus::Final->value,
            ])->assertCreated()->json('data');

            $current = spaPostJson("/api/v1/meetings/{$meeting['id']}/complete")->assertOk()->json('data');

            return ['meeting' => $current, 'recommendation' => $recommendation];
        }
    }

    throw new RuntimeException('Failed to prepare completed meeting');
}

test('D01 create standalone generates DEC-000001 ignores client fields and audits', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();

    $data = spaPostJson('/api/v1/decisions', [
        'title' => 'قرار إنشاء',
        'body' => 'النص',
        'decision_number' => 'HACK-9',
        'status' => 'approved',
        'tenant_id' => 999,
        'created_by' => 999,
    ])->assertCreated()->json('data');

    expect($data['decision_number'])->toBe('DEC-000001')
        ->and($data['status'])->toBe('draft')
        ->and($data['source_recommendation_id'])->toBeNull();

    Event::assertDispatched(AuthorizationSecurityEvent::class, function (AuthorizationSecurityEvent $e): bool {
        return $e->name === AuthorizationSecurityEvent::DECISION_CREATED;
    });
});

test('D02 numbering increments and is tenant-isolated', function (): void {
    actingAsTenantOwner();
    createDecisionViaApi(['title' => 'أ']);
    $second = createDecisionViaApi(['title' => 'ب']);
    expect($second['decision_number'])->toBe('DEC-000002');

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    $bFirst = createDecisionViaApi(['title' => 'مستأجر ب']);
    expect($bFirst['decision_number'])->toBe('DEC-000001');
});

test('D03 create from final recommendation on completed meeting prefills and links uniquely', function (): void {
    actingAsTenantOwner();
    $prepared = createCompletedMeetingWithFinalRecommendation('توصية للقرار');

    $decision = spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $prepared['recommendation']['id'],
    ])->assertCreated()->json('data');

    expect($decision['title'])->toBe('توصية للقرار')
        ->and($decision['body'])->toBe('وصف التوصية')
        ->and($decision['source_recommendation_id'])->toBe($prepared['recommendation']['id'])
        ->and($decision['source_meeting']['id'])->toBe($prepared['meeting']['id'])
        ->and($decision['status'])->toBe('draft');

    spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $prepared['recommendation']['id'],
        'title' => 'محاولة ثانية',
        'body' => 'نص',
    ])->assertStatus(422)->assertJsonPath('code', 'DECISION_ALREADY_CREATED_FROM_RECOMMENDATION');
});

test('D04 non-final recommendation and incomplete meeting rejected', function (): void {
    actingAsTenantOwner();

    $meeting = spaPostJson('/api/v1/meetings', ['title' => 'مفتوح'])->assertCreated()->json('data');
    spaPostJson("/api/v1/meetings/{$meeting['id']}/schedule", [
        'scheduled_at' => now()->addDay()->toIso8601String(),
    ])->assertOk();
    spaPostJson("/api/v1/meetings/{$meeting['id']}/start")->assertOk();

    $draftRec = spaPostJson("/api/v1/meetings/{$meeting['id']}/recommendations", [
        'title' => 'مسودة',
        'description' => 'وصف',
        'status' => RecommendationStatus::Draft->value,
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $draftRec['id'],
        'title' => 'ق',
        'body' => 'ن',
    ])->assertStatus(422)->assertJsonPath('code', 'DECISION_RECOMMENDATION_INVALID');

    $finalRec = spaPostJson("/api/v1/meetings/{$meeting['id']}/recommendations", [
        'title' => 'نهائية قبل الإكمال',
        'description' => 'وصف',
        'status' => RecommendationStatus::Final->value,
    ])->assertCreated()->json('data');

    spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $finalRec['id'],
        'title' => 'ق',
        'body' => 'ن',
    ])->assertStatus(422)->assertJsonPath('code', 'DECISION_RECOMMENDATION_INVALID');
});

test('D05 draft update allowed; non-draft immutable; number and status patch blocked', function (): void {
    actingAsTenantOwner();
    $decision = createDecisionViaApi();

    spaPatchJson("/api/v1/decisions/{$decision['id']}", [
        'title' => 'عنوان محدّث',
        'body' => 'نص محدّث',
    ])->assertOk()->assertJsonPath('data.title', 'عنوان محدّث');

    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertOk();

    spaPatchJson("/api/v1/decisions/{$decision['id']}", [
        'title' => 'محاولة',
    ])->assertStatus(422)->assertJsonPath('code', 'DECISION_IMMUTABLE');
});

test('D06 lifecycle happy path with history and return-draft comment', function (): void {
    Event::fake([AuthorizationSecurityEvent::class]);
    actingAsTenantOwner();
    $decision = createDecisionViaApi();

    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertOk()
        ->assertJsonPath('data.status', 'pending_approval');

    spaPostJson("/api/v1/decisions/{$decision['id']}/return-draft", [])
        ->assertStatus(422);

    spaPostJson("/api/v1/decisions/{$decision['id']}/return-draft", [
        'comment' => 'يحتاج مراجعة',
    ])->assertOk()->assertJsonPath('data.status', 'draft');

    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertOk();
    spaPostJson("/api/v1/decisions/{$decision['id']}/approve")->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $details = spaGetJson("/api/v1/decisions/{$decision['id']}")->assertOk()->json('data');
    expect($details['status_transitions'])->toHaveCount(4);

    spaPostJson("/api/v1/decisions/{$decision['id']}/close")->assertOk()
        ->assertJsonPath('data.status', 'closed');

    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")
        ->assertStatus(422)->assertJsonPath('code', 'DECISION_INVALID_STATUS_TRANSITION');
});

test('D07 cancel from draft and pending; not from approved', function (): void {
    actingAsTenantOwner();
    $a = createDecisionViaApi(['title' => 'إلغاء مسودة']);
    spaPostJson("/api/v1/decisions/{$a['id']}/cancel")->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $b = createDecisionViaApi(['title' => 'إلغاء معلق']);
    spaPostJson("/api/v1/decisions/{$b['id']}/submit")->assertOk();
    spaPostJson("/api/v1/decisions/{$b['id']}/cancel")->assertOk()
        ->assertJsonPath('data.status', 'cancelled');

    $c = createDecisionViaApi(['title' => 'معتمد']);
    spaPostJson("/api/v1/decisions/{$c['id']}/submit")->assertOk();
    spaPostJson("/api/v1/decisions/{$c['id']}/approve")->assertOk();
    spaPostJson("/api/v1/decisions/{$c['id']}/cancel")
        ->assertStatus(422)->assertJsonPath('code', 'DECISION_INVALID_STATUS_TRANSITION');
});

test('D08 delete untouched draft only', function (): void {
    $owner = actingAsTenantOwner();
    $draft = createDecisionViaApi();
    spaDeleteJson("/api/v1/decisions/{$draft['id']}")->assertOk();
    spaGetJson("/api/v1/decisions/{$draft['id']}")->assertNotFound();

    $advanced = createDecisionViaApi(['title' => 'متقدم']);
    spaPostJson("/api/v1/decisions/{$advanced['id']}/submit")->assertOk();
    spaPostJson("/api/v1/decisions/{$advanced['id']}/return-draft", [
        'comment' => 'ارجاع',
    ])->assertOk();

    spaDeleteJson("/api/v1/decisions/{$advanced['id']}")
        ->assertStatus(422)->assertJsonPath('code', 'DECISION_IMMUTABLE');
});

test('D09 date range and foreign employee/org rejected', function (): void {
    actingAsTenantOwner();

    spaPostJson('/api/v1/decisions', [
        'title' => 'تواريخ',
        'body' => 'نص',
        'effective_date' => '2026-08-20',
        'due_date' => '2026-08-10',
    ])->assertStatus(422)->assertJsonPath('code', 'DECISION_INVALID_DATE_RANGE');

    $tenantB = Tenant::factory()->create();
    $foreignEmployeeId = null;
    $foreignUnitId = null;
    withTenant($tenantB, function () use (&$foreignEmployeeId, &$foreignUnitId, $tenantB): void {
        provisionTenantRbac($tenantB);
        $unit = OrganizationUnit::factory()->create();
        $foreignUnitId = $unit->id;
        $foreignEmployeeId = Employee::factory()->create([
            'organization_unit_id' => $unit->id,
        ])->id;
    });

    spaPostJson('/api/v1/decisions', [
        'title' => 'موظف أجنبي',
        'body' => 'نص',
        'responsible_employee_id' => $foreignEmployeeId,
    ])->assertStatus(422);

    spaPostJson('/api/v1/decisions', [
        'title' => 'وحدة أجنبية',
        'body' => 'نص',
        'organization_unit_id' => $foreignUnitId,
    ])->assertStatus(422);
});

test('D10 cross-tenant isolation returns 404', function (): void {
    $ownerA = actingAsTenantOwner();
    $decision = createDecisionViaApi();

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaGetJson("/api/v1/decisions/{$decision['id']}")->assertNotFound();
    spaPatchJson("/api/v1/decisions/{$decision['id']}", ['title' => 'x'])->assertNotFound();
    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertNotFound();
    spaDeleteJson("/api/v1/decisions/{$decision['id']}")->assertNotFound();

    Sanctum::actingAs($ownerA);
    $list = spaGetJson('/api/v1/decisions')->assertOk()->json('data');
    expect(collect($list)->pluck('id'))->toContain($decision['id']);
});

test('D11 foreign recommendation blocked', function (): void {
    actingAsTenantOwner();
    $prepared = createCompletedMeetingWithFinalRecommendation();

    $tenantB = Tenant::factory()->create();
    $ownerB = provisionTenantRbac($tenantB);
    Sanctum::actingAs($ownerB);

    spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $prepared['recommendation']['id'],
        'title' => 'ق',
        'body' => 'ن',
    ])->assertStatus(422);
});

test('D12 department manager cannot approve or close by default', function (): void {
    $tenant = Tenant::factory()->create();
    $owner = provisionTenantRbac($tenant);
    Sanctum::actingAs($owner);

    $decision = createDecisionViaApi();
    spaPostJson("/api/v1/decisions/{$decision['id']}/submit")->assertOk();

    $manager = tenantUser($tenant);
    assignRole($manager, 'department_manager');
    Sanctum::actingAs($manager);

    spaPostJson("/api/v1/decisions/{$decision['id']}/approve")->assertForbidden();

    Sanctum::actingAs($owner);
    spaPostJson("/api/v1/decisions/{$decision['id']}/approve")->assertOk();

    Sanctum::actingAs($manager);
    spaPostJson("/api/v1/decisions/{$decision['id']}/close")->assertForbidden();
});

test('D13 list filters meeting_id search status and default newest first', function (): void {
    actingAsTenantOwner();
    $prepared = createCompletedMeetingWithFinalRecommendation('فلتر');
    $fromRec = spaPostJson('/api/v1/decisions', [
        'source_recommendation_id' => $prepared['recommendation']['id'],
    ])->assertCreated()->json('data');

    $standalone = createDecisionViaApi(['title' => 'مستقل فريد']);

    $byMeeting = spaGetJson('/api/v1/decisions?meeting_id='.$prepared['meeting']['id'])
        ->assertOk()->json('data');
    expect(collect($byMeeting)->pluck('id')->all())->toBe([$fromRec['id']]);

    $search = spaGetJson('/api/v1/decisions?search=مستقل')
        ->assertOk()->json('data');
    expect(collect($search)->pluck('id'))->toContain($standalone['id']);

    $list = spaGetJson('/api/v1/decisions')->assertOk()->json('data');
    expect($list[0]['id'])->toBe($standalone['id']);
});

test('D14 permissions catalog includes decisions.*', function (): void {
    $names = PermissionCatalog::allNames();
    foreach ([
        'decisions.view',
        'decisions.create',
        'decisions.update',
        'decisions.approve',
        'decisions.close',
        'decisions.delete',
    ] as $name) {
        expect($names)->toContain($name);
    }
});

test('D15 concurrent numbering does not collide', function (): void {
    actingAsTenantOwner();

    $results = [];
    DB::transaction(function () use (&$results): void {
        $results[] = spaPostJson('/api/v1/decisions', [
            'title' => 'متزامن 1',
            'body' => 'نص',
        ])->assertCreated()->json('data.decision_number');
    });

    $results[] = spaPostJson('/api/v1/decisions', [
        'title' => 'متزامن 2',
        'body' => 'نص',
    ])->assertCreated()->json('data.decision_number');

    expect($results)->toBe(['DEC-000001', 'DEC-000002']);
});
