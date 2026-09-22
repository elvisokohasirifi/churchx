<?php

use App\Models\Branch;
use App\Models\Expense;
use App\Models\ExpenseType;
use App\Models\FinancialAccount;
use App\Models\Fund;
use App\Models\GivingType;
use App\Models\Income;
use App\Models\PaymentMethod;
use App\Models\User;
use Database\Seeders\RoleAndPermissionSeeder;

beforeEach(function () {
    $this->seed(RoleAndPermissionSeeder::class);
});

it('allows only designated roles to create income', function (string $roleName, bool $allowed, bool $scoped) {
    $user = User::factory()->create();
    $branch = $scoped ? Branch::factory()->create() : null;
    assignRole($user, $roleName, $branch);

    $response = $this->actingAs($user, 'backpack')->get(route('income.create'));

    if ($allowed) {
        $response->assertOk();
        expect($user->can('income.create', $branch?->id))->toBeTrue();

        return;
    }

    $response->assertForbidden();
    expect($user->can('income.create'))->toBeFalse();
})->with([
    'app administrator' => ['App Administrator', true, false],
    'finance officer' => ['Finance Officer', true, false],
    'church leader' => ['Church Leader', true, false],
    'branch administrator' => ['Branch Administrator', true, true],
    'church administrator' => ['Church Administrator', false, false],
    'church overseer' => ['Church Overseer', false, false],
    'department leader' => ['Department Leader', false, false],
]);

it('shows income without the rest of finance to church and branch leaders', function (string $roleName, bool $scoped) {
    $user = User::factory()->create();
    $branch = $scoped ? Branch::factory()->create() : null;
    assignRole($user, $roleName, $branch);

    $response = $this->actingAs($user, 'backpack')->get(route('admin.dashboard'));

    $response
        ->assertSee('Finance')
        ->assertSee(backpack_url('income'))
        ->assertDontSee(backpack_url('offerings'))
        ->assertDontSee(backpack_url('expenses'))
        ->assertDontSee(backpack_url('expense-approvals/queue'))
        ->assertDontSee(backpack_url('account-transfers'))
        ->assertDontSee(backpack_url('pledges'))
        ->assertDontSee(backpack_url('reports/finance'));
})->with([
    'church leader' => ['Church Leader', false],
    'branch administrator' => ['Branch Administrator', true],
]);

it('allows only finance officers and app administrators to access all finance areas', function (string $roleName) {
    $user = User::factory()->create();
    assignRole($user, $roleName);

    $dashboard = $this->actingAs($user, 'backpack')->get(route('admin.dashboard'));

    $dashboard
        ->assertSee(backpack_url('income'))
        ->assertSee(backpack_url('offerings'))
        ->assertSee(backpack_url('expenses'))
        ->assertSee(backpack_url('expense-approvals/queue'))
        ->assertSee(backpack_url('account-transfers'))
        ->assertSee(backpack_url('pledges'))
        ->assertSee(backpack_url('reports/finance'));

    $this->get(route('expenses.create'))->assertOk();
    $this->get(route('admin.offerings.index'))->assertOk();
    $this->get(route('admin.expenses.approvals'))->assertOk();
    $this->get(route('admin.transfers.index'))->assertOk();
    $this->get(route('pledges.create'))->assertOk();
    $this->get(route('admin.reports.finance'))->assertOk();
})->with(['Finance Officer', 'App Administrator']);

it('forbids non-finance roles from finance areas other than permitted income entry', function (string $roleName) {
    $user = User::factory()->create();
    assignRole($user, $roleName);

    $this->actingAs($user, 'backpack')->get(route('expenses.create'))->assertForbidden();
    $this->get(route('admin.offerings.index'))->assertForbidden();
    $this->get(route('admin.expenses.approvals'))->assertForbidden();
    $this->get(route('admin.transfers.index'))->assertForbidden();
    $this->get(route('pledges.create'))->assertForbidden();
    $this->get(route('admin.reports.finance'))->assertForbidden();
})->with(['Church Leader', 'Church Administrator', 'Church Overseer', 'Branch Administrator']);

it('keeps the finance officer interface limited to finance', function () {
    $user = User::factory()->create();
    assignRole($user, 'Finance Officer');

    $response = $this->actingAs($user, 'backpack')->get(route('admin.dashboard'));

    $response
        ->assertSee('Finance')
        ->assertSee('Completed income')
        ->assertSee('Paid expenses')
        ->assertDontSee(backpack_url('events'))
        ->assertDontSee(backpack_url('members'))
        ->assertDontSee('Latest attendance')
        ->assertDontSee('>Members<', escape: false)
        ->assertDontSee('>Visitors<', escape: false)
        ->assertDontSee('>Branches<', escape: false);

    $this->get(route('events.index'))->assertForbidden();
});

it('shows the 20 most recent incomes and expenses on the finance dashboard', function () {
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer');
    $branch = Branch::factory()->create(['name' => 'Central Branch']);
    $givingType = GivingType::factory()->create(['name' => 'Sunday Giving']);
    $expenseType = ExpenseType::factory()->create(['name' => 'Service Supplies']);
    $fund = Fund::factory()->create();
    $paymentMethod = PaymentMethod::factory()->create();
    $financialAccount = FinancialAccount::factory()->create(['branch_id' => $branch->id]);

    foreach (range(1, 21) as $day) {
        Income::factory()->create([
            'branch_id' => $branch->id,
            'giving_type_id' => $givingType->id,
            'fund_id' => $fund->id,
            'payment_method_id' => $paymentMethod->id,
            'financial_account_id' => $financialAccount->id,
            'giver_name' => 'Income giver '.$day,
            'date' => sprintf('2026-01-%02d', $day),
        ]);
        Expense::factory()->create([
            'branch_id' => $branch->id,
            'expense_type_id' => $expenseType->id,
            'fund_id' => $fund->id,
            'payment_method_id' => $paymentMethod->id,
            'financial_account_id' => $financialAccount->id,
            'recipient_name' => 'Expense recipient '.$day,
            'date' => sprintf('2026-01-%02d', $day),
        ]);
    }

    $response = $this->actingAs($financeOfficer, 'backpack')->get(route('admin.dashboard'));

    $response
        ->assertSee('20 most recent incomes')
        ->assertSee('20 most recent expenses')
        ->assertSee('Income giver 21')
        ->assertDontSee('Income giver 1<', escape: false)
        ->assertSee('Expense recipient 21')
        ->assertDontSee('Expense recipient 1<', escape: false);
    expect($response->viewData('recentIncomes'))
        ->toHaveCount(20)
        ->and($response->viewData('recentIncomes')->first()->giver_name)->toBe('Income giver 21')
        ->and($response->viewData('recentIncomes')->last()->giver_name)->toBe('Income giver 2');
    expect($response->viewData('recentExpenses'))
        ->toHaveCount(20)
        ->and($response->viewData('recentExpenses')->first()->recipient_name)->toBe('Expense recipient 21')
        ->and($response->viewData('recentExpenses')->last()->recipient_name)->toBe('Expense recipient 2');
});

it('limits recent finance dashboard transactions to assigned branches', function () {
    $assignedBranch = Branch::factory()->create(['name' => 'Assigned Branch']);
    $otherBranch = Branch::factory()->create(['name' => 'Other Branch']);
    $financeOfficer = User::factory()->create();
    assignRole($financeOfficer, 'Finance Officer', $assignedBranch);

    Income::factory()->create(['branch_id' => $assignedBranch->id, 'giver_name' => 'Visible income']);
    Income::factory()->create(['branch_id' => $otherBranch->id, 'giver_name' => 'Hidden income']);
    Expense::factory()->create(['branch_id' => $assignedBranch->id, 'recipient_name' => 'Visible expense']);
    Expense::factory()->create(['branch_id' => $otherBranch->id, 'recipient_name' => 'Hidden expense']);

    $response = $this->actingAs($financeOfficer, 'backpack')->get(route('admin.dashboard'));

    $response
        ->assertSee('Visible income')
        ->assertDontSee('Hidden income')
        ->assertSee('Visible expense')
        ->assertDontSee('Hidden expense');
    expect($response->viewData('recentIncomes'))->toHaveCount(1)
        ->and($response->viewData('recentExpenses'))->toHaveCount(1);
});
