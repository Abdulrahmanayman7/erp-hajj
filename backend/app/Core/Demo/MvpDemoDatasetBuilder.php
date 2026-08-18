<?php

namespace App\Core\Demo;

use App\Core\Authorization\Actions\BootstrapTenantOwner;
use App\Core\Authorization\PermissionCatalogSynchronizer;
use App\Core\Authorization\ProvisionDefaultTenantRoles;
use App\Core\Shared\CorrelationId;
use App\Core\Tenancy\Models\Tenant;
use App\Core\Tenancy\TenantContext;
use App\Core\Tenancy\TenantStatus;
use App\Models\User;
use App\Modules\Assets\Actions\AssignCustody;
use App\Modules\Assets\Actions\CreateAsset;
use App\Modules\Assets\Actions\CreateAssetCategory;
use App\Modules\Assets\Actions\SendAssetToMaintenance;
use App\Modules\Assets\Enums\AssetCondition;
use App\Modules\Authorization\Models\Role;
use App\Modules\Contracts\Actions\ApproveContract;
use App\Modules\Contracts\Actions\CreateContract;
use App\Modules\Contracts\Actions\CreateContractCategory;
use App\Modules\Contracts\Actions\ExecuteContract;
use App\Modules\Contracts\Actions\SignContract;
use App\Modules\Contracts\Actions\SubmitContractForReview;
use App\Modules\Contracts\Enums\CounterpartyKind;
use App\Modules\Decisions\Actions\ApproveDecision;
use App\Modules\Decisions\Actions\CreateDecision;
use App\Modules\Decisions\Actions\SubmitDecisionForApproval;
use App\Modules\Documents\Actions\CreateDocumentCategory;
use App\Modules\Documents\Actions\UploadDocument;
use App\Modules\Employees\Actions\AssignEmployeeSupervisor;
use App\Modules\Employees\Actions\CreateEmployee;
use App\Modules\Employees\Actions\CreatePosition;
use App\Modules\Employees\Actions\LinkEmployeeUser;
use App\Modules\Employees\Models\Employee;
use App\Modules\Employees\Models\Position;
use App\Modules\Inventory\Actions\CreateInventoryCategory;
use App\Modules\Inventory\Actions\CreateInventoryItem;
use App\Modules\Inventory\Actions\CreateWarehouse;
use App\Modules\Inventory\Actions\IssueStock;
use App\Modules\Inventory\Actions\ReceiveStock;
use App\Modules\Inventory\Enums\InventoryUnit;
use App\Modules\Meetings\Actions\AddMeetingAttendee;
use App\Modules\Meetings\Actions\CompleteMeeting;
use App\Modules\Meetings\Actions\CreateAgendaItem;
use App\Modules\Meetings\Actions\CreateMeeting;
use App\Modules\Meetings\Actions\CreateMeetingRecommendation;
use App\Modules\Meetings\Actions\ScheduleMeeting;
use App\Modules\Meetings\Actions\StartMeeting;
use App\Modules\Meetings\Actions\UpdateMeetingMinutes;
use App\Modules\Meetings\Enums\MeetingLocationType;
use App\Modules\Meetings\Enums\RecommendationStatus;
use App\Modules\OrganizationStructure\Actions\CreateOrganizationUnit;
use App\Modules\OrganizationStructure\Enums\OrganizationUnitType;
use App\Modules\OrganizationStructure\Models\OrganizationUnit;
use App\Modules\Settings\Actions\UpdateTenantSettings;
use App\Modules\Tasks\Actions\CompleteTask;
use App\Modules\Tasks\Actions\CreateTask;
use App\Modules\Tasks\Actions\StartTask;
use App\Modules\Tasks\Enums\TaskPriority;
use App\Modules\Users\Actions\CreateUser;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Deterministic Demo/UAT dataset for tenant rafee.
 * Prefer domain Actions so lifecycle, audit, and invariants stay intact.
 */
final class MvpDemoDatasetBuilder
{
    public const DEMO_PASSWORD = 'Demo@123456';

    public const OWNER_EMAIL = 'owner@rafee.demo.local';

    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CorrelationId $correlationId,
        private readonly PermissionCatalogSynchronizer $catalogSynchronizer,
        private readonly ProvisionDefaultTenantRoles $provisionRoles,
        private readonly BootstrapTenantOwner $bootstrapOwner,
        private readonly CreateUser $createUser,
        private readonly CreateOrganizationUnit $createOrgUnit,
        private readonly CreatePosition $createPosition,
        private readonly CreateEmployee $createEmployee,
        private readonly LinkEmployeeUser $linkEmployeeUser,
        private readonly AssignEmployeeSupervisor $assignSupervisor,
        private readonly CreateContractCategory $createContractCategory,
        private readonly CreateContract $createContract,
        private readonly SubmitContractForReview $submitContract,
        private readonly ApproveContract $approveContract,
        private readonly SignContract $signContract,
        private readonly ExecuteContract $executeContract,
        private readonly CreateMeeting $createMeeting,
        private readonly ScheduleMeeting $scheduleMeeting,
        private readonly StartMeeting $startMeeting,
        private readonly UpdateMeetingMinutes $updateMinutes,
        private readonly CompleteMeeting $completeMeeting,
        private readonly CreateAgendaItem $createAgendaItem,
        private readonly AddMeetingAttendee $addAttendee,
        private readonly CreateMeetingRecommendation $createRecommendation,
        private readonly CreateDecision $createDecision,
        private readonly SubmitDecisionForApproval $submitDecision,
        private readonly ApproveDecision $approveDecision,
        private readonly CreateTask $createTask,
        private readonly StartTask $startTask,
        private readonly CompleteTask $completeTask,
        private readonly CreateWarehouse $createWarehouse,
        private readonly CreateInventoryCategory $createInventoryCategory,
        private readonly CreateInventoryItem $createInventoryItem,
        private readonly ReceiveStock $receiveStock,
        private readonly IssueStock $issueStock,
        private readonly CreateAssetCategory $createAssetCategory,
        private readonly CreateAsset $createAsset,
        private readonly AssignCustody $assignCustody,
        private readonly SendAssetToMaintenance $sendToMaintenance,
        private readonly CreateDocumentCategory $createDocumentCategory,
        private readonly UploadDocument $uploadDocument,
        private readonly UpdateTenantSettings $updateSettings,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function seed(?Command $command = null): array
    {
        $this->correlationId->set('demo-mvp-'.Str::uuid()->toString());

        if (User::query()->where('email', self::OWNER_EMAIL)->exists()) {
            throw new \RuntimeException(
                'Demo dataset already present (owner@rafee.demo.local). Drop/recreate erp_hajj_demo if a full rebuild is required.',
            );
        }

        $command?->info('Syncing permission catalog + ensuring tenant rafee...');
        $this->catalogSynchronizer->sync();

        $tenant = Tenant::query()->firstOrCreate(
            ['tenant_code' => 'rafee'],
            [
                'name' => 'رفيع',
                'status' => TenantStatus::Active,
                'locale' => 'ar',
                'timezone' => 'Asia/Riyadh',
                'notes' => 'Demo/UAT tenant — بيانات تجريبية للعرض فقط',
            ],
        );

        $this->provisionRoles->execute($tenant, null);

        $ownerResult = $this->bootstrapOwner->execute(
            'rafee',
            'عبدالله الرفاعي',
            self::OWNER_EMAIL,
            self::DEMO_PASSWORD,
            false,
            false,
        );

        /** @var User $owner */
        $owner = User::query()->findOrFail($ownerResult['user_id']);

        return $this->tenantContext->runAsTenant($tenant, function () use ($tenant, $owner, $command): array {
            $request = $this->demoRequest();
            $tz = $tenant->timezone ?: 'Asia/Riyadh';
            $today = now($tz)->startOfDay();

            $command?->info('Updating tenant settings (audit)...');
            $this->updateSettings->execute($owner, [
                'general' => [
                    'name' => 'رفيع لإدارة حملات الحج',
                    'contact_name' => 'مكتب إدارة رفيع',
                    'contact_email' => 'ops@rafee.demo.local',
                    'contact_phone' => '+966500000001',
                ],
                'regional' => [
                    'timezone' => 'Asia/Riyadh',
                ],
            ], $request);

            $command?->info('Seeding roles users, organization, employees...');
            $roles = Role::query()->get()->keyBy('code');
            $users = $this->seedUsers($owner, $roles, $request);
            $units = $this->seedOrganization($owner, $users, $request);
            $positions = $this->seedPositions($owner, $request);
            $employees = $this->seedEmployees($owner, $users, $units, $positions, $request);

            $command?->info('Seeding contracts...');
            $contracts = $this->seedContracts($owner, $units, $employees, $today, $request);

            $command?->info('Seeding meetings → decisions → tasks...');
            $governance = $this->seedGovernance($owner, $units, $employees, $today, $request);

            $command?->info('Seeding warehouses/inventory...');
            $inventory = $this->seedInventory($owner, $units, $employees, $request);

            $command?->info('Seeding assets/custodies...');
            $assets = $this->seedAssets($owner, $units, $employees, $inventory['warehouses'], $today, $request);

            $command?->info('Seeding documents...');
            $documents = $this->seedDocuments($owner, $governance['completed_meeting_id'] ?? null, $request);

            return [
                'tenant' => $tenant->tenant_code,
                'owner_email' => self::OWNER_EMAIL,
                'users' => count($users) + 1,
                'organization_units' => count($units),
                'positions' => count($positions),
                'employees' => count($employees),
                'contracts' => $contracts,
                'meetings' => $governance['meetings'] ?? 0,
                'decisions' => $governance['decisions'] ?? 0,
                'tasks' => $governance['tasks'] ?? 0,
                'warehouses' => count($inventory['warehouses']),
                'inventory_items' => count($inventory['items']),
                'assets' => $assets['assets'] ?? 0,
                'custodies_active' => $assets['custodies'] ?? 0,
                'documents' => $documents,
                'demo_password' => self::DEMO_PASSWORD,
            ];
        });
    }

    private function demoRequest(): Request
    {
        $request = Request::create('/demo/seed', 'POST', [], [], [], [
            'HTTP_USER_AGENT' => 'MvpDemoDatasetBuilder/1.0',
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $request->headers->set('X-Correlation-ID', $this->correlationId->get() ?? (string) Str::uuid());

        return $request;
    }

    /**
     * @param  Collection<string, Role>  $roles
     * @return array<string, User>
     */
    private function seedUsers(User $owner, $roles, Request $request): array
    {
        $defs = [
            'gm' => ['name' => 'فهد العتيبي', 'email' => 'gm@rafee.demo.local', 'role' => 'general_manager'],
            'ops_mgr' => ['name' => 'سعود الحربي', 'email' => 'ops.manager@rafee.demo.local', 'role' => 'department_manager'],
            'transport_mgr' => ['name' => 'ناصر القحطاني', 'email' => 'transport.manager@rafee.demo.local', 'role' => 'department_manager'],
            'housing_mgr' => ['name' => 'مشعل الدوسري', 'email' => 'housing.manager@rafee.demo.local', 'role' => 'department_manager'],
            'warehouse_mgr' => ['name' => 'تركي الشمري', 'email' => 'warehouse.manager@rafee.demo.local', 'role' => 'department_manager'],
            'supervisor_field' => ['name' => 'بندر السبيعي', 'email' => 'supervisor.field@rafee.demo.local', 'role' => 'supervisor'],
            'supervisor_ops' => ['name' => 'ماجد العنزي', 'email' => 'supervisor.ops@rafee.demo.local', 'role' => 'supervisor'],
            'employee_transport' => ['name' => 'خالد الغامدي', 'email' => 'employee.transport@rafee.demo.local', 'role' => 'employee'],
            'employee_housing' => ['name' => 'يوسف المطيري', 'email' => 'employee.housing@rafee.demo.local', 'role' => 'employee'],
            'employee_warehouse' => ['name' => 'راكان الزهراني', 'email' => 'employee.warehouse@rafee.demo.local', 'role' => 'employee'],
            'auditor' => ['name' => 'إبراهيم الشهري', 'email' => 'auditor@rafee.demo.local', 'role' => 'auditor'],
        ];

        $users = [];
        foreach ($defs as $key => $def) {
            $role = $roles->get($def['role']);
            if ($role === null) {
                throw new \RuntimeException("Missing system role: {$def['role']}");
            }

            $result = $this->createUser->execute($owner, [
                'name' => $def['name'],
                'email' => $def['email'],
                'role_ids' => [$role->id],
                'send_invite' => false,
                'temporary_password' => self::DEMO_PASSWORD,
            ], $request);

            /** @var User $user */
            $user = $result['user'];
            // Ensure known demo password even if CreateUser hashed a provisioned one.
            $user->forceFill(['password' => Hash::make(self::DEMO_PASSWORD)])->save();
            $users[$key] = $user->fresh();
        }

        return $users;
    }

    /**
     * @param  array<string, User>  $users
     * @return array<string, OrganizationUnit>
     */
    private function seedOrganization(User $owner, array $users, Request $request): array
    {
        $exec = $this->createOrgUnit->execute($owner, [
            'name' => 'الإدارة العليا',
            'code' => 'EXEC',
            'type' => OrganizationUnitType::Department->value,
            'sort_order' => 1,
            'manager_user_id' => $users['gm']->id,
        ], $request);

        $ops = $this->createOrgUnit->execute($owner, [
            'name' => 'إدارة العمليات والحج',
            'code' => 'OPS',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 2,
            'manager_user_id' => $users['ops_mgr']->id,
        ], $request);

        $transport = $this->createOrgUnit->execute($owner, [
            'name' => 'إدارة النقل والتفويج',
            'code' => 'TRN',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 3,
            'manager_user_id' => $users['transport_mgr']->id,
        ], $request);

        $housing = $this->createOrgUnit->execute($owner, [
            'name' => 'إدارة الإسكان',
            'code' => 'HSG',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 4,
            'manager_user_id' => $users['housing_mgr']->id,
        ], $request);

        $catering = $this->createOrgUnit->execute($owner, [
            'name' => 'إدارة الإعاشة',
            'code' => 'CAT',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 5,
        ], $request);

        $warehouse = $this->createOrgUnit->execute($owner, [
            'name' => 'إدارة المستودعات واللوجستيات',
            'code' => 'WH',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 6,
            'manager_user_id' => $users['warehouse_mgr']->id,
        ], $request);

        $hr = $this->createOrgUnit->execute($owner, [
            'name' => 'الإدارة الإدارية والموارد البشرية',
            'code' => 'HR',
            'type' => OrganizationUnitType::Department->value,
            'parent_id' => $exec->id,
            'sort_order' => 7,
        ], $request);

        $field = $this->createOrgUnit->execute($owner, [
            'name' => 'وحدة الإشراف الميداني',
            'code' => 'OPS-FIELD',
            'type' => OrganizationUnitType::Unit->value,
            'parent_id' => $ops->id,
            'sort_order' => 1,
            'manager_user_id' => $users['supervisor_field']->id,
        ], $request);

        return compact('exec', 'ops', 'transport', 'housing', 'catering', 'warehouse', 'hr', 'field');
    }

    /**
     * @return array<string, Position>
     */
    private function seedPositions(User $owner, Request $request): array
    {
        $defs = [
            'gm' => ['name' => 'المدير العام', 'code' => 'POS-GM'],
            'ops' => ['name' => 'مدير العمليات', 'code' => 'POS-OPS'],
            'transport' => ['name' => 'مدير النقل والتفويج', 'code' => 'POS-TRN'],
            'housing' => ['name' => 'مدير الإسكان', 'code' => 'POS-HSG'],
            'warehouse' => ['name' => 'مسؤول المستودع', 'code' => 'POS-WH'],
            'supervisor' => ['name' => 'مشرف ميداني', 'code' => 'POS-SUP'],
            'coordinator' => ['name' => 'منسق عمليات', 'code' => 'POS-COORD'],
            'specialist' => ['name' => 'أخصائي تشغيل', 'code' => 'POS-SPEC'],
            'auditor' => ['name' => 'مدقق داخلي', 'code' => 'POS-AUD'],
        ];

        $out = [];
        foreach ($defs as $key => $def) {
            $out[$key] = $this->createPosition->execute($owner, $def, $request);
        }

        return $out;
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, OrganizationUnit>  $units
     * @param  array<string, mixed>  $positions
     * @return array<string, Employee>
     */
    private function seedEmployees(User $owner, array $users, array $units, array $positions, Request $request): array
    {
        $defs = [
            'owner' => [
                'full_name' => 'عبدالله الرفاعي',
                'email' => self::OWNER_EMAIL,
                'unit' => 'exec',
                'position' => 'gm',
                'user' => null,
                'phone' => '+966500000010',
            ],
            'gm' => [
                'full_name' => 'فهد العتيبي',
                'email' => 'gm@rafee.demo.local',
                'unit' => 'exec',
                'position' => 'gm',
                'user' => 'gm',
                'phone' => '+966500000011',
            ],
            'ops_mgr' => [
                'full_name' => 'سعود الحربي',
                'email' => 'ops.manager@rafee.demo.local',
                'unit' => 'ops',
                'position' => 'ops',
                'user' => 'ops_mgr',
                'phone' => '+966500000012',
            ],
            'transport_mgr' => [
                'full_name' => 'ناصر القحطاني',
                'email' => 'transport.manager@rafee.demo.local',
                'unit' => 'transport',
                'position' => 'transport',
                'user' => 'transport_mgr',
                'phone' => '+966500000013',
            ],
            'housing_mgr' => [
                'full_name' => 'مشعل الدوسري',
                'email' => 'housing.manager@rafee.demo.local',
                'unit' => 'housing',
                'position' => 'housing',
                'user' => 'housing_mgr',
                'phone' => '+966500000014',
            ],
            'warehouse_mgr' => [
                'full_name' => 'تركي الشمري',
                'email' => 'warehouse.manager@rafee.demo.local',
                'unit' => 'warehouse',
                'position' => 'warehouse',
                'user' => 'warehouse_mgr',
                'phone' => '+966500000015',
            ],
            'supervisor_field' => [
                'full_name' => 'بندر السبيعي',
                'email' => 'supervisor.field@rafee.demo.local',
                'unit' => 'field',
                'position' => 'supervisor',
                'user' => 'supervisor_field',
                'phone' => '+966500000016',
                'supervisor' => 'ops_mgr',
            ],
            'supervisor_ops' => [
                'full_name' => 'ماجد العنزي',
                'email' => 'supervisor.ops@rafee.demo.local',
                'unit' => 'ops',
                'position' => 'coordinator',
                'user' => 'supervisor_ops',
                'phone' => '+966500000017',
                'supervisor' => 'ops_mgr',
            ],
            'employee_transport' => [
                'full_name' => 'خالد الغامدي',
                'email' => 'employee.transport@rafee.demo.local',
                'unit' => 'transport',
                'position' => 'specialist',
                'user' => 'employee_transport',
                'phone' => '+966500000018',
                'supervisor' => 'transport_mgr',
            ],
            'employee_housing' => [
                'full_name' => 'يوسف المطيري',
                'email' => 'employee.housing@rafee.demo.local',
                'unit' => 'housing',
                'position' => 'specialist',
                'user' => 'employee_housing',
                'phone' => '+966500000019',
                'supervisor' => 'housing_mgr',
            ],
            'employee_warehouse' => [
                'full_name' => 'راكان الزهراني',
                'email' => 'employee.warehouse@rafee.demo.local',
                'unit' => 'warehouse',
                'position' => 'specialist',
                'user' => 'employee_warehouse',
                'phone' => '+966500000020',
                'supervisor' => 'warehouse_mgr',
            ],
            'auditor' => [
                'full_name' => 'إبراهيم الشهري',
                'email' => 'auditor@rafee.demo.local',
                'unit' => 'hr',
                'position' => 'auditor',
                'user' => 'auditor',
                'phone' => '+966500000021',
            ],
            'no_login_1' => [
                'full_name' => 'سلمان العسيري',
                'email' => 'salman.assiri@rafee.demo.local',
                'unit' => 'catering',
                'position' => 'specialist',
                'user' => null,
                'phone' => '+966500000022',
            ],
            'no_login_2' => [
                'full_name' => 'علي الشهري',
                'email' => 'ali.shehri@rafee.demo.local',
                'unit' => 'transport',
                'position' => 'coordinator',
                'user' => null,
                'phone' => '+966500000023',
            ],
            'no_login_3' => [
                'full_name' => 'حسن المالكي',
                'email' => 'hassan.maliki@rafee.demo.local',
                'unit' => 'housing',
                'position' => 'coordinator',
                'user' => null,
                'phone' => '+966500000024',
            ],
        ];

        $employees = [];
        foreach ($defs as $key => $def) {
            $employee = $this->createEmployee->execute($owner, [
                'full_name' => $def['full_name'],
                'email' => $def['email'],
                'phone' => $def['phone'],
                'organization_unit_id' => $units[$def['unit']]->id,
                'position_id' => $positions[$def['position']]->id,
                'hire_date' => now()->subMonths(rand(3, 24))->toDateString(),
            ], $request);

            if ($key === 'owner') {
                $this->linkEmployeeUser->execute($owner, $employee, $owner->id, $request);
            } elseif ($def['user'] !== null) {
                $this->linkEmployeeUser->execute($owner, $employee, $users[$def['user']]->id, $request);
            }

            $employees[$key] = $employee->fresh();
        }

        foreach ($defs as $key => $def) {
            if (! isset($def['supervisor'])) {
                continue;
            }
            $this->assignSupervisor->execute(
                $owner,
                $employees[$key],
                $employees[$def['supervisor']]->id,
                $request,
            );
        }

        return $employees;
    }

    /**
     * @param  array<string, OrganizationUnit>  $units
     * @param  array<string, Employee>  $employees
     * @return array<string, int>
     */
    private function seedContracts(User $owner, array $units, array $employees, $today, Request $request): array
    {
        $transportCat = $this->createContractCategory->execute($owner, [
            'name' => 'عقود النقل والتفويج',
            'code' => 'CAT-TRN',
        ], $request);
        $housingCat = $this->createContractCategory->execute($owner, [
            'name' => 'عقود الإسكان',
            'code' => 'CAT-HSG',
        ], $request);
        $supplyCat = $this->createContractCategory->execute($owner, [
            'name' => 'عقود التوريد',
            'code' => 'CAT-SUP',
        ], $request);

        $advance = function (array $data) use ($owner, $request) {
            $contract = $this->createContract->execute($owner, $data, $request);
            $this->submitContract->execute($owner, $contract, null, $request);
            $contract = $contract->fresh();
            $this->approveContract->execute($owner, $contract, null, $request);
            $contract = $contract->fresh();
            $this->signContract->execute($owner, $contract, null, $request);
            $contract = $contract->fresh();
            $this->executeContract->execute($owner, $contract, null, $request);

            return $contract->fresh();
        };

        $advance([
            'title' => 'عقد تفويج الحافلات للموسم',
            'contract_category_id' => $transportCat->id,
            'counterparty_name' => 'شركة النخبة للنقل',
            'counterparty_kind' => CounterpartyKind::Organization->value,
            'organization_unit_id' => $units['transport']->id,
            'employee_id' => $employees['transport_mgr']->id,
            'start_date' => $today->copy()->subMonths(2)->toDateString(),
            'end_date' => $today->copy()->addMonths(4)->toDateString(),
            'value' => 850000,
            'currency' => 'SAR',
            'notes' => 'عقد تشغيل نشط للموسم',
        ]);

        $advance([
            'title' => 'عقد إسكان المشاعر',
            'contract_category_id' => $housingCat->id,
            'counterparty_name' => 'مؤسسة سكن الحجاج',
            'counterparty_kind' => CounterpartyKind::Organization->value,
            'organization_unit_id' => $units['housing']->id,
            'employee_id' => $employees['housing_mgr']->id,
            'start_date' => $today->copy()->subMonth()->toDateString(),
            'end_date' => $today->copy()->addDays(12)->toDateString(),
            'value' => 1200000,
            'currency' => 'SAR',
            'notes' => 'ينتهي قريبًا — للتنبيه في اللوحة',
        ]);

        $advance([
            'title' => 'عقد توريد مياه موسمي',
            'contract_category_id' => $supplyCat->id,
            'counterparty_name' => 'شركة واحة المياه',
            'counterparty_kind' => CounterpartyKind::Organization->value,
            'organization_unit_id' => $units['warehouse']->id,
            'employee_id' => $employees['warehouse_mgr']->id,
            'start_date' => $today->copy()->subMonths(3)->toDateString(),
            'end_date' => $today->copy()->subDays(10)->toDateString(),
            'value' => 220000,
            'currency' => 'SAR',
            'notes' => 'منتهي — سيُحوَّل عبر contracts:expire',
        ]);

        $draft = $this->createContract->execute($owner, [
            'title' => 'مسودة عقد صيانة أجهزة اتصال',
            'contract_category_id' => $supplyCat->id,
            'counterparty_name' => 'مؤسسة الاتصالات الميدانية',
            'counterparty_kind' => CounterpartyKind::Organization->value,
            'organization_unit_id' => $units['ops']->id,
            'start_date' => $today->copy()->addDays(7)->toDateString(),
            'end_date' => $today->copy()->addMonths(6)->toDateString(),
            'value' => 45000,
            'currency' => 'SAR',
        ], $request);

        $inReview = $this->createContract->execute($owner, [
            'title' => 'عقد إعاشة تحت المراجعة',
            'contract_category_id' => $supplyCat->id,
            'counterparty_name' => 'مطابخ المشاعر',
            'counterparty_kind' => CounterpartyKind::Organization->value,
            'organization_unit_id' => $units['catering']->id,
            'start_date' => $today->copy()->addDays(14)->toDateString(),
            'end_date' => $today->copy()->addMonths(3)->toDateString(),
            'value' => 310000,
            'currency' => 'SAR',
        ], $request);
        $this->submitContract->execute($owner, $inReview, null, $request);

        return [
            'executing' => 3,
            'draft' => 1,
            'in_review' => 1,
            'draft_id' => $draft->id,
        ];
    }

    /**
     * @param  array<string, OrganizationUnit>  $units
     * @param  array<string, Employee>  $employees
     * @return array<string, mixed>
     */
    private function seedGovernance(User $owner, array $units, array $employees, $today, Request $request): array
    {
        // Completed governance chain: meeting → recommendation → decision → tasks
        $completed = $this->createMeeting->execute($owner, [
            'title' => 'اجتماع خطة النقل والتفويج',
            'description' => 'اعتماد توزيع الحافلات ومسارات التفويج للموسم',
            'organization_unit_id' => $units['transport']->id,
            'chairperson_employee_id' => $employees['gm']->id,
            'secretary_employee_id' => $employees['transport_mgr']->id,
            'location_type' => MeetingLocationType::Physical->value,
            'location_text' => 'قاعة الاجتماعات — المقر الرئيسي',
        ], $request);

        $agenda = $this->createAgendaItem->execute($owner, $completed, [
            'title' => 'مراجعة كشف الحافلات والمسارات',
            'sort_order' => 1,
        ], $request);

        foreach (['transport_mgr', 'supervisor_field', 'employee_transport', 'ops_mgr'] as $key) {
            $this->addAttendee->execute($owner, $completed, $employees[$key]->id, $request);
        }

        $pastAt = $today->copy()->subDays(3)->setTime(10, 0)->toIso8601String();
        $this->scheduleMeeting->execute($owner, $completed, $pastAt, null, $request);
        $completed = $completed->fresh();
        $this->startMeeting->execute($owner, $completed, null, $request);
        $completed = $completed->fresh();

        $recommendation = $this->createRecommendation->execute($owner, $completed, [
            'title' => 'اعتماد خطة توزيع الحافلات',
            'description' => 'اعتماد التوزيع النهائي وتكليف إدارة النقل بالمتابعة التشغيلية',
            'agenda_item_id' => $agenda->id,
            'owner_employee_id' => $employees['transport_mgr']->id,
            'status' => RecommendationStatus::Draft->value,
            'sort_order' => 1,
        ], $request);

        $this->updateMinutes->execute(
            $owner,
            $completed->fresh(),
            "محضر اجتماع خطة النقل والتفويج\nتم استعراض كشف الحافلات واعتماد التوصية بخطة التوزيع النهائي.",
            $request,
        );

        $this->completeMeeting->execute($owner, $completed->fresh(), null, $request);
        $recommendation = $recommendation->fresh();

        $decision = $this->createDecision->execute($owner, [
            'title' => 'قرار اعتماد خطة توزيع الحافلات',
            'body' => 'تُعتمد خطة توزيع الحافلات للموسم وتُكلَّف إدارة النقل والتفويج بتنفيذها ومتابعة الالتزام بالمسارات المعتمدة.',
            'source_recommendation_id' => $recommendation->id,
            'organization_unit_id' => $units['transport']->id,
            'issued_by_employee_id' => $employees['gm']->id,
            'responsible_employee_id' => $employees['transport_mgr']->id,
            'effective_date' => $today->toDateString(),
            'due_date' => $today->copy()->addDays(14)->toDateString(),
        ], $request);
        $this->submitDecision->execute($owner, $decision, null, $request);
        $decision = $decision->fresh();
        $this->approveDecision->execute($owner, $decision, null, $request);
        $decision = $decision->fresh();

        // Tasks linked to approved decision
        $overdue = $this->createTask->execute($owner, [
            'title' => 'مراجعة كشف الحافلات النهائي',
            'description' => 'مهمة متأخرة مرتبطة بقرار خطة النقل',
            'decision_id' => $decision->id,
            'organization_unit_id' => $units['transport']->id,
            'assigned_to_employee_id' => $employees['employee_transport']->id,
            'priority' => TaskPriority::High->value,
            'start_date' => $today->copy()->subDays(10)->toDateString(),
            'due_date' => $today->copy()->subDays(2)->toDateString(),
        ], $request);
        $this->startTask->execute($owner, $overdue, null, $request);

        $dueToday = $this->createTask->execute($owner, [
            'title' => 'تأكيد نقاط التجمع مع المشرفين',
            'decision_id' => $decision->id,
            'organization_unit_id' => $units['transport']->id,
            'assigned_to_employee_id' => $employees['supervisor_field']->id,
            'priority' => TaskPriority::High->value,
            'start_date' => $today->copy()->subDays(1)->toDateString(),
            'due_date' => $today->toDateString(),
        ], $request);

        $upcoming = $this->createTask->execute($owner, [
            'title' => 'إعداد تقرير الالتزام بالمسارات',
            'decision_id' => $decision->id,
            'organization_unit_id' => $units['transport']->id,
            'assigned_to_employee_id' => $employees['transport_mgr']->id,
            'priority' => TaskPriority::Medium->value,
            'start_date' => $today->toDateString(),
            'due_date' => $today->copy()->addDays(5)->toDateString(),
        ], $request);

        $completedTask = $this->createTask->execute($owner, [
            'title' => 'أرشفة مسودة خطة التوزيع',
            'decision_id' => $decision->id,
            'organization_unit_id' => $units['transport']->id,
            'assigned_to_employee_id' => $employees['employee_transport']->id,
            'priority' => TaskPriority::Low->value,
            'start_date' => $today->copy()->subDays(7)->toDateString(),
            'due_date' => $today->copy()->subDays(4)->toDateString(),
        ], $request);
        $this->startTask->execute($owner, $completedTask, null, $request);
        $this->completeTask->execute($owner, $completedTask->fresh(), 'تم أرشفة المسودة في مجلد التشغيل الموسمي.', $request);

        // Pending decision (standalone)
        $pending = $this->createDecision->execute($owner, [
            'title' => 'قرار اعتماد جدول الإشراف الميداني',
            'body' => 'مقترح باعتماد جدول المناوبات للمشرفين الميدانيين خلال أيام الذروة.',
            'organization_unit_id' => $units['ops']->id,
            'issued_by_employee_id' => $employees['ops_mgr']->id,
            'responsible_employee_id' => $employees['supervisor_ops']->id,
            'due_date' => $today->copy()->addDays(7)->toDateString(),
        ], $request);
        $this->submitDecision->execute($owner, $pending, null, $request);

        // Today / upcoming / in-progress meetings
        $todayMeeting = $this->createMeeting->execute($owner, [
            'title' => 'اجتماع تجهيز السكن',
            'description' => 'متابعة جاهزية الوحدات السكنية قبل الوصول',
            'organization_unit_id' => $units['housing']->id,
            'chairperson_employee_id' => $employees['housing_mgr']->id,
            'secretary_employee_id' => $employees['employee_housing']->id,
            'location_type' => MeetingLocationType::Hybrid->value,
            'location_text' => 'قاعة الإسكان',
        ], $request);
        $this->scheduleMeeting->execute(
            $owner,
            $todayMeeting,
            $today->copy()->setTime(11, 0)->toIso8601String(),
            null,
            $request,
        );

        $upcomingMeeting = $this->createMeeting->execute($owner, [
            'title' => 'اجتماع متابعة المستودعات',
            'organization_unit_id' => $units['warehouse']->id,
            'chairperson_employee_id' => $employees['warehouse_mgr']->id,
            'location_type' => MeetingLocationType::Physical->value,
            'location_text' => 'المستودع الرئيسي',
        ], $request);
        $this->scheduleMeeting->execute(
            $owner,
            $upcomingMeeting,
            $today->copy()->addDays(2)->setTime(9, 30)->toIso8601String(),
            null,
            $request,
        );

        $inProgress = $this->createMeeting->execute($owner, [
            'title' => 'اجتماع الاستعداد للموسم',
            'organization_unit_id' => $units['ops']->id,
            'chairperson_employee_id' => $employees['ops_mgr']->id,
            'secretary_employee_id' => $employees['supervisor_ops']->id,
            'location_type' => MeetingLocationType::Physical->value,
            'location_text' => 'قاعة العمليات',
        ], $request);
        $this->scheduleMeeting->execute(
            $owner,
            $inProgress,
            $today->copy()->setTime(8, 0)->toIso8601String(),
            null,
            $request,
        );
        $this->startMeeting->execute($owner, $inProgress->fresh(), null, $request);

        // Meeting starting soon (for scanner)
        $soon = $this->createMeeting->execute($owner, [
            'title' => 'اجتماع تنسيقي قصير — التفويج',
            'organization_unit_id' => $units['transport']->id,
            'chairperson_employee_id' => $employees['transport_mgr']->id,
            'location_type' => MeetingLocationType::Remote->value,
            'meeting_link' => 'https://meet.demo.local/rafee-ops',
        ], $request);
        $this->scheduleMeeting->execute(
            $owner,
            $soon,
            now('Asia/Riyadh')->addMinutes(25)->toIso8601String(),
            null,
            $request,
        );

        return [
            'meetings' => 5,
            'decisions' => 2,
            'tasks' => 4,
            'completed_meeting_id' => $completed->id,
            'approved_decision_id' => $decision->id,
            'task_overdue_id' => $overdue->id,
            'task_due_today_id' => $dueToday->id,
            'task_upcoming_id' => $upcoming->id,
        ];
    }

    /**
     * @param  array<string, OrganizationUnit>  $units
     * @param  array<string, Employee>  $employees
     * @return array{warehouses: array<string, mixed>, items: array<string, mixed>}
     */
    private function seedInventory(User $owner, array $units, array $employees, Request $request): array
    {
        $main = $this->createWarehouse->execute($owner, [
            'name' => 'المستودع الرئيسي',
            'description' => 'مستودع المقر واللوازم العامة',
            'location' => 'جدة — المنطقة الصناعية',
            'organization_unit_id' => $units['warehouse']->id,
            'responsible_employee_id' => $employees['warehouse_mgr']->id,
        ], $request);

        $mashair = $this->createWarehouse->execute($owner, [
            'name' => 'مستودع المشاعر',
            'description' => 'مستودع تشغيلي قرب المشاعر',
            'location' => 'مكة — منطقة المشاعر',
            'organization_unit_id' => $units['warehouse']->id,
            'responsible_employee_id' => $employees['employee_warehouse']->id,
        ], $request);

        $catWater = $this->createInventoryCategory->execute($owner, ['name' => 'مياه ومشروبات'], $request);
        $catClean = $this->createInventoryCategory->execute($owner, ['name' => 'مستلزمات نظافة'], $request);
        $catOps = $this->createInventoryCategory->execute($owner, ['name' => 'مستلزمات تشغيل'], $request);
        $catOffice = $this->createInventoryCategory->execute($owner, ['name' => 'أدوات مكتبية'], $request);

        $water = $this->createInventoryItem->execute($owner, [
            'name' => 'كراتين مياه 330مل',
            'category_id' => $catWater->id,
            'unit' => InventoryUnit::Box->value,
            'minimum_stock' => 50,
            'barcode' => 'DEMO-WATER-330',
        ], $request);

        $badges = $this->createInventoryItem->execute($owner, [
            'name' => 'بطاقات تعريف مشرفين',
            'category_id' => $catOps->id,
            'unit' => InventoryUnit::Piece->value,
            'minimum_stock' => 100,
            'barcode' => 'DEMO-BADGE-SUP',
        ], $request);

        $vests = $this->createInventoryItem->execute($owner, [
            'name' => 'ستر مشرفين عاكسة',
            'category_id' => $catOps->id,
            'unit' => InventoryUnit::Piece->value,
            'minimum_stock' => 40,
            'barcode' => 'DEMO-VEST-SUP',
        ], $request);

        $cleaner = $this->createInventoryItem->execute($owner, [
            'name' => 'منظف أرضيات مركّز',
            'category_id' => $catClean->id,
            'unit' => InventoryUnit::Liter->value,
            'minimum_stock' => 30,
            'barcode' => 'DEMO-CLEAN-01',
        ], $request);

        $stationery = $this->createInventoryItem->execute($owner, [
            'name' => 'دفاتر متابعة ميدانية',
            'category_id' => $catOffice->id,
            'unit' => InventoryUnit::Pack->value,
            'minimum_stock' => 10,
            'barcode' => 'DEMO-NOTE-01',
        ], $request);

        // Opening balances via ReceiveStock
        $this->receiveStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $water->id,
            'quantity' => 200,
            'reason' => 'رصيد افتتاحي للموسم',
            'as_opening' => true,
            'reference' => 'DEMO-OPEN-WATER',
        ], $request);

        $this->receiveStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $badges->id,
            'quantity' => 120,
            'reason' => 'رصيد افتتاحي',
            'as_opening' => true,
        ], $request);

        $this->receiveStock->execute($owner, [
            'warehouse_id' => $mashair->id,
            'inventory_item_id' => $vests->id,
            'quantity' => 45,
            'reason' => 'رصيد افتتاحي مستودع المشاعر',
            'as_opening' => true,
        ], $request);

        $this->receiveStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $cleaner->id,
            'quantity' => 35,
            'reason' => 'رصيد افتتاحي',
            'as_opening' => true,
        ], $request);

        $this->receiveStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $stationery->id,
            'quantity' => 25,
            'reason' => 'رصيد افتتاحي',
            'as_opening' => true,
        ], $request);

        // Drive low / out via issues (no negative stock)
        $this->issueStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $badges->id,
            'quantity' => 95,
            'reason' => 'صرف للمشرفين الميدانيين',
        ], $request); // 25 left < min 100 → low

        $this->issueStock->execute($owner, [
            'warehouse_id' => $mashair->id,
            'inventory_item_id' => $vests->id,
            'quantity' => 45,
            'reason' => 'صرف كامل لفريق التفويج',
        ], $request); // 0 → out

        $this->issueStock->execute($owner, [
            'warehouse_id' => $main->id,
            'inventory_item_id' => $cleaner->id,
            'quantity' => 20,
            'reason' => 'صرف لفرق النظافة',
        ], $request); // 15 < 30 → low

        return [
            'warehouses' => ['main' => $main, 'mashair' => $mashair],
            'items' => compact('water', 'badges', 'vests', 'cleaner', 'stationery'),
        ];
    }

    /**
     * @param  array<string, OrganizationUnit>  $units
     * @param  array<string, Employee>  $employees
     * @param  array<string, mixed>  $warehouses
     * @return array{assets: int, custodies: int}
     */
    private function seedAssets(User $owner, array $units, array $employees, array $warehouses, $today, Request $request): array
    {
        $catIt = $this->createAssetCategory->execute($owner, [
            'name' => 'أجهزة حاسب',
            'description' => 'لابتوبات وأجهزة لوحية',
        ], $request);
        $catComm = $this->createAssetCategory->execute($owner, [
            'name' => 'أجهزة اتصال',
        ], $request);
        $catOffice = $this->createAssetCategory->execute($owner, [
            'name' => 'معدات مكتبية',
        ], $request);

        $laptop = $this->createAsset->execute($owner, [
            'name' => 'لابتوب تشغيل — العمليات',
            'category_id' => $catIt->id,
            'serial_number' => 'DEMO-LAP-001',
            'warehouse_id' => $warehouses['main']->id,
            'organization_unit_id' => $units['ops']->id,
            'condition' => AssetCondition::Good->value,
        ], $request);

        $tablet = $this->createAsset->execute($owner, [
            'name' => 'جهاز لوحي ميداني',
            'category_id' => $catIt->id,
            'serial_number' => 'DEMO-TAB-001',
            'warehouse_id' => $warehouses['mashair']->id,
            'organization_unit_id' => $units['field']->id,
            'condition' => AssetCondition::Good->value,
        ], $request);

        $radio = $this->createAsset->execute($owner, [
            'name' => 'جهاز اتصال لاسلكي',
            'category_id' => $catComm->id,
            'serial_number' => 'DEMO-RAD-001',
            'organization_unit_id' => $units['ops']->id,
            'condition' => AssetCondition::Good->value,
        ], $request);

        $printer = $this->createAsset->execute($owner, [
            'name' => 'طابعة مكتبية متعددة الوظائف',
            'category_id' => $catOffice->id,
            'serial_number' => 'DEMO-PRT-001',
            'warehouse_id' => $warehouses['main']->id,
            'organization_unit_id' => $units['hr']->id,
            'condition' => AssetCondition::Good->value,
        ], $request);

        $spareLaptop = $this->createAsset->execute($owner, [
            'name' => 'لابتوب احتياطي',
            'category_id' => $catIt->id,
            'serial_number' => 'DEMO-LAP-002',
            'warehouse_id' => $warehouses['main']->id,
            'organization_unit_id' => $units['ops']->id,
            'condition' => AssetCondition::Good->value,
        ], $request);

        $this->assignCustody->execute($owner, $tablet, [
            'employee_id' => $employees['supervisor_field']->id,
            'expected_return_at' => $today->copy()->addDays(2)->setTime(17, 0)->toIso8601String(),
            'assignment_notes' => 'عهدة موسم للمشرف الميداني',
            'condition_at_assignment' => AssetCondition::Good->value,
        ], $request);

        $this->assignCustody->execute($owner, $radio, [
            'employee_id' => $employees['supervisor_ops']->id,
            'expected_return_at' => $today->copy()->subDays(1)->setTime(17, 0)->toIso8601String(),
            'assignment_notes' => 'عهدة متأخرة عن موعد الإرجاع المتوقع',
            'condition_at_assignment' => AssetCondition::Good->value,
        ], $request);

        $this->sendToMaintenance->execute($owner, $printer, $request);

        return ['assets' => 5, 'custodies' => 2, 'available' => $spareLaptop->id, 'laptop' => $laptop->id];
    }

    private function seedDocuments(User $owner, ?int $meetingId, Request $request): int
    {
        $cat = $this->createDocumentCategory->execute($owner, [
            'name' => 'وثائق تشغيل الموسم',
            'description' => 'خطط ومحاضر وتعليمات تشغيلية',
        ], $request);

        $files = [
            ['title' => 'خطة تشغيل الموسم', 'name' => 'season-ops-plan.txt', 'body' => "خطة تشغيل موسم الحج — بيانات تجريبية فقط.\n"],
            ['title' => 'محضر اجتماع خطة النقل', 'name' => 'transport-meeting-minutes.txt', 'body' => "محضر اجتماع خطة النقل والتفويج — تجريبي.\n", 'link_meeting' => true],
            ['title' => 'تعليمات النقل والتفويج', 'name' => 'transport-instructions.txt', 'body' => "تعليمات تشغيلية للنقل — تجريبي.\n"],
            ['title' => 'كشف تجهيز المستودع', 'name' => 'warehouse-checklist.txt', 'body' => "كشف تجهيز المستودع الرئيسي — تجريبي.\n"],
        ];

        $count = 0;
        foreach ($files as $file) {
            $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.$file['name'];
            file_put_contents($path, $file['body']);
            $uploaded = new UploadedFile($path, $file['name'], 'text/plain', null, true);

            $data = [
                'title' => $file['title'],
                'description' => 'مستند تجريبي للعرض (Demo/UAT)',
                'category_id' => $cat->id,
            ];
            if (($file['link_meeting'] ?? false) && $meetingId !== null) {
                $data['linkable_type'] = 'meeting';
                $data['linkable_id'] = $meetingId;
            }

            $this->uploadDocument->execute($owner, $uploaded, $data, $request);
            @unlink($path);
            $count++;
        }

        return $count;
    }
}
