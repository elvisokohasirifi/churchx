<?php

use App\IncomeStatus;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\BranchDepartment;
use App\Models\Church;
use App\Models\ChurchGroup;
use App\Models\Department;
use App\Models\DepartmentRole;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\Member;
use App\Models\PaymentMethod;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleAndPermissionSeeder;

it('renders the authenticated administration surfaces', function (string $path) {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $this->actingAs($admin, 'backpack')->get('/admin/'.$path)->assertOk();
})->with([
    'dashboard', 'members', 'services', 'attendance/capture', 'events', 'departments', 'groups',
    'payment-methods', 'funds', 'giving-types', 'financial-accounts', 'income', 'offerings',
    'expense-types', 'expenses', 'expense-approvals/queue', 'pledges', 'account-transfers',
    'assets', 'asset-types', 'audit-logs', 'reports/finance', 'reports/attendance', 'reports/members',
    'reports/attendance-records', 'reports/attendance-leaders', 'reports/attendance-churches',
    'reports/giving-records', 'reports/giving-leaders', 'reports/absence-follow-up',
    'broadcasts/compose',
]);

it('shows the page search throughout the administration area', function (string $path) {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $this->actingAs($admin, 'backpack')
        ->get('/admin/'.$path)
        ->assertOk()
        ->assertSee('data-admin-menu-search', false)
        ->assertSee('Search pages...');
})->with([
    'dashboard', 'members', 'members/create',
]);

it('renders representative free CRUD forms', function (string $path) {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $this->actingAs($admin, 'backpack')->get('/admin/'.$path.'/create')->assertOk();
})->with([
    'services', 'events', 'departments', 'branch-departments', 'department-roles', 'department-members',
    'groups', 'group-members', 'leadership-titles', 'financial-accounts', 'income', 'expenses', 'pledges',
    'assets', 'giving-types', 'funds', 'payment-methods', 'expense-types',
]);

it('shows finance reference data managers in the admin navigation', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/dashboard');

    $response
        ->assertSee('Giving Types')
        ->assertSee('Funds')
        ->assertSee('Payment Methods')
        ->assertSee('Expense Types')
        ->assertSee(backpack_url('giving-types'))
        ->assertSee(backpack_url('funds'))
        ->assertSee(backpack_url('payment-methods'))
        ->assertSee(backpack_url('expense-types'));
});

it('shows ministry and leadership management screens in the admin navigation', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/dashboard');

    $response
        ->assertSee('Branch Departments')
        ->assertSee('Department Roles')
        ->assertSee('Department Members')
        ->assertSee('Cells / Groups')
        ->assertSee('Cell / Group Members')
        ->assertSee('Leadership Titles')
        ->assertSee(backpack_url('branch-departments'))
        ->assertSee(backpack_url('department-members'))
        ->assertSee(backpack_url('group-members'))
        ->assertSee(backpack_url('leadership-titles'));
});

it('shows branch-scoped ministry screens to branch administrators', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $branch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $branch);

    $response = $this->actingAs($administrator, 'backpack')->get('/admin/dashboard');

    $response
        ->assertSee('Branch Departments')
        ->assertSee('Department Members')
        ->assertSee('Cells / Groups')
        ->assertSee('Cell / Group Members');
});

it('shows church-wide cells and groups to church-wide administrators', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    ChurchGroup::query()->create([
        'name' => 'Church-wide Young Adults',
        'type' => 'fellowship',
        'branch_id' => null,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->post(route('groups.search'), ['start' => 0, 'length' => 25]);

    $response->assertOk()->assertSee('Church-wide Young Adults');
});

it('creates ministry structures, memberships, and leadership titles', function (string $resource) {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $branch = Branch::factory()->create();
    $member = Member::factory()->create();

    [$routeName, $table, $payload, $expected] = match ($resource) {
        'branch department' => (function () use ($branch): array {
            $department = Department::query()->create(['name' => 'Media']);

            return ['branch-departments.store', 'branch_departments', ['branch_id' => $branch->id, 'department_id' => $department->id, 'is_active' => true], ['branch_id' => $branch->id, 'department_id' => $department->id]];
        })(),
        'department member' => (function () use ($branch, $member): array {
            $department = Department::query()->create(['name' => 'Media']);
            $branchDepartment = BranchDepartment::query()->create(['branch_id' => $branch->id, 'department_id' => $department->id, 'is_active' => true]);
            $departmentRole = DepartmentRole::query()->create(['name' => 'Team Member']);

            return ['department-members.store', 'branch_department_members', ['branch_department_id' => $branchDepartment->id, 'member_id' => $member->id, 'department_role_id' => $departmentRole->id, 'joined_date' => '2026-09-21', 'is_active' => true], ['branch_department_id' => $branchDepartment->id, 'member_id' => $member->id]];
        })(),
        'cell / group' => ['groups.store', 'church_groups', ['name' => 'Grace Cell', 'type' => 'cell', 'branch_id' => $branch->id, 'description' => 'Weekly cell meeting'], ['name' => 'Grace Cell', 'type' => 'cell']],
        'cell / group member' => (function () use ($branch, $member): array {
            $group = ChurchGroup::query()->create(['name' => 'Grace Cell', 'type' => 'cell', 'branch_id' => $branch->id]);

            return ['group-members.store', 'group_members', ['group_id' => $group->id, 'member_id' => $member->id, 'joined_date' => '2026-09-21', 'role' => 'Leader', 'is_active' => true], ['group_id' => $group->id, 'member_id' => $member->id]];
        })(),
        'leadership title' => ['leadership-titles.store', 'leadership_titles', ['name' => 'Cell Coordinator', 'description' => 'Coordinates church cells'], ['name' => 'Cell Coordinator']],
    };

    $response = $this->actingAs($admin, 'backpack')->post(route($routeName), $payload);

    $response->assertRedirect()->assertSessionHasNoErrors();
    $this->assertDatabaseHas($table, $expected);
})->with(['branch department', 'department member', 'cell / group', 'cell / group member', 'leadership title']);

it('creates finance reference data used by dropdowns', function (string $routeName, string $table, array $payload) {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->post(route($routeName), $payload);

    $response->assertRedirect();
    $this->assertDatabaseHas($table, ['name' => $payload['name']]);
})->with([
    'giving type' => ['giving-types.store', 'giving_types', ['name' => 'Special Offering', 'requires_giver' => false, 'is_active' => true]],
    'fund' => ['funds.store', 'funds', ['name' => 'Youth Ministry Fund', 'restricted' => true, 'is_active' => true]],
    'payment method' => ['payment-methods.store', 'payment_methods', ['name' => 'Online Gateway', 'is_active' => true]],
    'expense type' => ['expense-types.store', 'expense_types', ['name' => 'Hospitality', 'is_active' => true]],
]);

it('shows configured finance values in transaction dropdowns', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $fund = Fund::factory()->create(['name' => 'Community Outreach Fund']);
    GivingType::factory()->create(['name' => 'Outreach Offering', 'default_fund_id' => $fund->id]);
    PaymentMethod::factory()->create(['name' => 'Church Mobile App']);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/income/create');

    $response
        ->assertSee('Community Outreach Fund')
        ->assertSee('Outreach Offering')
        ->assertSee('Church Mobile App')
        ->assertSee('Giving Type')
        ->assertSee('Fund')
        ->assertSee('Payment Method')
        ->assertSee('Financial Account')
        ->assertDontSee('Giving Type Id')
        ->assertDontSee('Fund Id')
        ->assertDontSee('Payment Method Id')
        ->assertDontSee('Financial Account Id');
});

it('shows relationship labels instead of ids in configured listings', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $fund = Fund::factory()->create(['name' => 'Building Project Fund']);
    GivingType::factory()->create(['name' => 'Building Offering', 'default_fund_id' => $fund->id]);
    $branch = Branch::factory()->create(['name' => 'North Campus']);
    $department = Department::query()->create(['name' => 'Media Department']);
    BranchDepartment::query()->create([
        'branch_id' => $branch->id,
        'department_id' => $department->id,
        'is_active' => true,
    ]);
    $givingTypesResponse = $this->actingAs($admin, 'backpack')->post(route('giving-types.search'), ['start' => 0, 'length' => 25]);
    $branchDepartmentsResponse = $this->actingAs($admin, 'backpack')->post(route('branch-departments.search'), ['start' => 0, 'length' => 25]);

    $givingTypesResponse
        ->assertOk()
        ->assertSee('Building Project Fund')
        ->assertDontSee($fund->id);
    $branchDepartmentsResponse
        ->assertOk()
        ->assertSee('North Campus')
        ->assertSee('Media Department')
        ->assertDontSee($branch->id)
        ->assertDontSee($department->id);
});

it('shows polymorphic and user relationship labels in audit listings', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create(['name' => 'System Administrator']);
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $fund = Fund::factory()->create(['name' => 'Building Project Fund']);
    AuditLog::query()->create([
        'user_id' => $admin->id,
        'action' => 'fund.updated',
        'auditable_type' => Fund::class,
        'auditable_id' => $fund->id,
        'changes' => [],
        'created_at' => now(),
    ]);

    $auditLogsResponse = $this->actingAs($admin, 'backpack')->post(route('audit-logs.search'), ['start' => 0, 'length' => 25]);

    $auditLogsResponse
        ->assertOk()
        ->assertSee('Building Project Fund')
        ->assertSee('System Administrator')
        ->assertDontSee($fund->id)
        ->assertDontSee($admin->id);
});

it('renders finance relationships as dropdowns when their source tables are empty', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/income/create');
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);

    foreach (['giving_type_id', 'fund_id', 'payment_method_id', 'financial_account_id'] as $field) {
        expect($xpath->query("//select[@name='{$field}']")->length)->toBe(1);
    }

    $response
        ->assertSee('Add one in Finance settings')
        ->assertDontSee('<input type="text" name="giving_type_id"', escape: false)
        ->assertDontSee('<input type="text" name="fund_id"', escape: false)
        ->assertDontSee('<input type="text" name="payment_method_id"', escape: false)
        ->assertDontSee('<input type="text" name="financial_account_id"', escape: false);
});

it('defaults new financial records to the church currency', function (string $path, string $fieldAttribute) {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create(['currency' => 'EUR']);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/'.$path);
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);

    expect($xpath->query("//input[@{$fieldAttribute}='currency' and @value='EUR']")->length)->toBe(1);
})->with([
    'financial account' => ['financial-accounts/create', 'name'],
    'income' => ['income/create', 'name'],
    'expense' => ['expenses/create', 'name'],
    'asset' => ['assets/create', 'name'],
    'offering capture' => ['offerings/capture', 'data-field'],
    'account transfer' => ['account-transfers', 'name'],
]);

it('keeps an existing record currency when editing', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    Church::factory()->create(['currency' => 'EUR']);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $account = FinancialAccount::factory()->create(['currency' => 'GBP']);

    $response = $this->actingAs($admin, 'backpack')->get(route('financial-accounts.edit', $account));

    $response
        ->assertSee('value="GBP"', escape: false)
        ->assertDontSee('value="EUR"', escape: false);
});

it('renders the allowed income statuses as a dropdown', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/income/create');
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);
    $options = [];

    foreach ($xpath->query("//select[@name='status']/option") as $option) {
        $options[$option->getAttribute('value')] = trim($option->textContent);
    }

    expect($options)->toBe([
        IncomeStatus::Pending->value => 'Pending',
        IncomeStatus::Completed->value => 'Completed',
        IncomeStatus::Reversed->value => 'Reversed',
        IncomeStatus::Cancelled->value => 'Cancelled',
    ])->and($xpath->query("//select[@name='status']/option[@value='completed' and @selected]")->length)->toBe(1);
});

it('defaults new income dates to today', function () {
    $this->travelTo('2026-03-14 09:00:00');
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->get('/admin/income/create');
    $document = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $document->loadHTML($response->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $xpath = new DOMXPath($document);

    expect($xpath->query("//input[@name='date' and @value='2026-03-14']")->length)->toBe(1);
});

it('rejects an unsupported income status', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);

    $response = $this->actingAs($admin, 'backpack')->post(route('income.store'), ['status' => 'refunded']);

    $response->assertSessionHasErrors('status');
    $this->assertDatabaseCount('incomes', 0);
});

it('provides each giving type default fund to income and offering forms', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $admin = User::factory()->create();
    UserRole::query()->create(['user_id' => $admin->id, 'role_id' => Role::query()->where('name', 'App Administrator')->firstOrFail()->id, 'is_active' => true]);
    $fund = Fund::factory()->create(['name' => 'Designated Missions Fund']);
    $givingType = GivingType::factory()->create(['name' => 'Missions Gift', 'default_fund_id' => $fund->id]);

    $incomeResponse = $this->actingAs($admin, 'backpack')->get('/admin/income/create');
    $offeringResponse = $this->actingAs($admin, 'backpack')->get('/admin/offerings/capture');

    $incomeDocument = new DOMDocument;
    $previousErrorHandling = libxml_use_internal_errors(true);
    $incomeDocument->loadHTML($incomeResponse->getContent());
    $offeringDocument = new DOMDocument;
    $offeringDocument->loadHTML($offeringResponse->getContent());
    libxml_clear_errors();
    libxml_use_internal_errors($previousErrorHandling);
    $incomeSelect = (new DOMXPath($incomeDocument))->query("//select[@name='giving_type_id']")->item(0);
    $offeringOption = (new DOMXPath($offeringDocument))->query("//option[@value='{$givingType->id}' and @data-default-fund='{$fund->id}']");
    $defaultFunds = json_decode($incomeSelect->getAttribute('data-giving-type-default-funds'), true, flags: JSON_THROW_ON_ERROR);

    expect($defaultFunds[$givingType->id])->toBe($fund->id)
        ->and($incomeSelect->getAttribute('data-sync-default-on-load'))->toBe('1')
        ->and($offeringOption->length)->toBe(1);
});
