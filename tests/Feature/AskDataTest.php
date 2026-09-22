<?php

use App\ExpenseStatus;
use App\IncomeStatus;
use App\MemberBranchStatus;
use App\MemberStatus;
use App\Models\AttendanceSummary;
use App\Models\Branch;
use App\Models\BranchLeader;
use App\Models\Expense;
use App\Models\Income;
use App\Models\LeadershipTitle;
use App\Models\Member;
use App\Models\MemberBranch;
use App\Models\Role;
use App\Models\Service;
use App\Models\User;
use App\Models\UserRole;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $this->travelTo('2026-09-21 12:00:00');
});

it('shows ask data and only the examples allowed by the user role', function () {
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');

    $response = $this->actingAs($financeOfficer, 'backpack')->get(route('admin.ask-data.index'));

    $response
        ->assertSee('Ask Data')
        ->assertSee('Income vs expenses')
        ->assertSee('Finance by branch')
        ->assertDontSee('Attendance by branch')
        ->assertDontSee('Members by branch')
        ->assertSee('Permission-aware analytics');
});

it('compares attendance only across branches in the user scope', function () {
    $assignedBranch = Branch::factory()->create(['name' => 'Visible Church']);
    $otherBranch = Branch::factory()->create(['name' => 'Hidden Church']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $assignedBranch);
    $visibleService = Service::factory()->create(['branch_id' => $assignedBranch->id, 'date' => '2026-05-03']);
    $hiddenService = Service::factory()->create(['branch_id' => $otherBranch->id, 'date' => '2026-05-03']);
    AttendanceSummary::factory()->create([
        'service_id' => $visibleService->id,
        'branch_id' => $assignedBranch->id,
        'total_male' => 10,
        'total_female' => 20,
        'total_children' => 5,
    ]);
    AttendanceSummary::factory()->create([
        'service_id' => $hiddenService->id,
        'branch_id' => $otherBranch->id,
        'total_male' => 100,
        'total_female' => 100,
        'total_children' => 100,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Compare attendance by churches',
        'from' => '2026-01-01',
        'to' => '2026-12-31',
    ]);

    $response
        ->assertSee('Attendance by church branch')
        ->assertSee('Visible Church')
        ->assertDontSee('Hidden Church')
        ->assertSee('data-ask-data-chart', escape: false)
        ->assertSee('Supporting data');
    expect($response->viewData('result')['rows'])->toBe([
        ['branch' => 'Visible Church', 'services' => 1, 'attendance' => 35, 'average' => 35.0],
    ]);
});

it('compares monthly income and expenses for finance users', function () {
    $branch = Branch::factory()->create();
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');
    Income::factory()->create([
        'branch_id' => $branch->id,
        'amount' => '500.0000',
        'date' => '2026-04-10',
        'status' => IncomeStatus::Completed,
    ]);
    Expense::factory()->create([
        'branch_id' => $branch->id,
        'amount' => '125.0000',
        'date' => '2026-04-12',
        'status' => ExpenseStatus::Paid,
    ]);

    $response = $this->actingAs($financeOfficer, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Compare monthly income and expenses',
        'from' => '2026-04-01',
        'to' => '2026-04-30',
    ]);

    $response
        ->assertSee('Income and expenses over time')
        ->assertSee('500.00')
        ->assertSee('125.00')
        ->assertSee('375.00');
    expect($response->viewData('result')['rows'])->toBe([
        ['period' => '2026-04', 'income' => 500.0, 'expenses' => 125.0, 'net' => 375.0],
    ]);
});

it('compares attendance by active church leaders', function () {
    $branch = Branch::factory()->create(['name' => 'North Church']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');
    $member = Member::factory()->create(['first_name' => 'Ama', 'middle_name' => null, 'last_name' => 'Mensah']);
    $title = LeadershipTitle::query()->create(['name' => 'Branch Pastor']);
    BranchLeader::query()->create([
        'branch_id' => $branch->id,
        'member_id' => $member->id,
        'leadership_title_id' => $title->id,
        'start_date' => '2025-01-01',
        'is_active' => true,
    ]);
    $service = Service::factory()->create(['branch_id' => $branch->id, 'date' => '2026-08-02']);
    AttendanceSummary::factory()->create([
        'service_id' => $service->id,
        'branch_id' => $branch->id,
        'total_male' => 20,
        'total_female' => 25,
        'total_children' => 10,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Compare attendance by church leaders this year',
    ]);

    $response
        ->assertSee('Attendance by church leader')
        ->assertSee('Ama Mensah')
        ->assertSee('Branch Pastor')
        ->assertSee('North Church');
    expect($response->viewData('result')['rows'][0]['attendance'])->toBe(55);
});

it('compares members only across branches in the user scope', function () {
    $assignedBranch = Branch::factory()->create(['name' => 'Member Scope Church']);
    $otherBranch = Branch::factory()->create(['name' => 'Private Member Church']);
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $assignedBranch);
    $visibleMember = Member::factory()->create();
    $hiddenMember = Member::factory()->create();
    MemberBranch::query()->create([
        'member_id' => $visibleMember->id,
        'branch_id' => $assignedBranch->id,
        'joined_date' => '2026-01-01',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);
    MemberBranch::query()->create([
        'member_id' => $hiddenMember->id,
        'branch_id' => $otherBranch->id,
        'joined_date' => '2026-01-01',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Show members by church branch',
    ]);

    $response
        ->assertSee('Members by church branch')
        ->assertSee('Member Scope Church')
        ->assertDontSee('Private Member Church');
    expect($response->viewData('result')['rows'])->toBe([
        ['branch' => 'Member Scope Church', 'members' => 1],
    ]);
});

it('formats enum-backed membership statuses in analytics results', function () {
    $branch = Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'Branch Administrator', $branch);
    $member = Member::factory()->create(['membership_status' => MemberStatus::NewConvert]);
    MemberBranch::query()->create([
        'member_id' => $member->id,
        'branch_id' => $branch->id,
        'joined_date' => '2026-01-01',
        'is_primary' => true,
        'status' => MemberBranchStatus::Active,
    ]);

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Break down members by membership status',
    ]);

    $response->assertSee('New Convert');
    expect($response->viewData('result')['rows'])->toBe([
        ['status' => 'New Convert', 'members' => 1],
    ]);
});

it('recognizes supported analytics questions', function (string $question, string $title) {
    Branch::factory()->create();
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => $question,
    ]);

    $response->assertSee($title);
    expect($response->viewData('result')['title'])->toBe($title);
})->with([
    'attendance trend' => ['Show the attendance trend this year', 'Attendance trend'],
    'membership status' => ['Break down members by membership status', 'Members by membership status'],
    'giving types' => ['Show income by giving type this year', 'Income by giving type'],
    'expense types' => ['Show expenses by expense type this year', 'Expenses by type'],
    'branch finances' => ['Compare finances by church branch this year', 'Finance by church branch'],
]);

it('forbids questions about a data domain outside the user permissions', function () {
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');

    $response = $this->actingAs($financeOfficer, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => 'Compare attendance by church branch',
    ]);

    $response->assertForbidden();
});

it('forbids the page when an active role has no analytics permissions', function () {
    $role = Role::factory()->create(['name' => 'Communications Volunteer']);
    $user = User::factory()->create();
    UserRole::query()->create([
        'user_id' => $user->id,
        'role_id' => $role->id,
        'is_active' => true,
        'assigned_at' => now(),
    ]);

    $response = $this->actingAs($user, 'backpack')->get(route('admin.ask-data.index'));

    $response->assertForbidden();
});

it('validates the question and date range', function () {
    $administrator = User::factory()->create();
    assignRole($administrator, 'App Administrator');

    $response = $this->actingAs($administrator, 'backpack')->post(route('admin.ask-data.answer'), [
        'question' => '',
        'from' => '2026-05-02',
        'to' => '2026-05-01',
    ]);

    $response
        ->assertSessionHasErrors(['question', 'to'])
        ->assertSessionHas('errors', fn ($errors): bool => $errors->get('question')[0] === 'Ask a question about your church data.'
            && $errors->get('to')[0] === 'The end date must be on or after the start date.');
});
