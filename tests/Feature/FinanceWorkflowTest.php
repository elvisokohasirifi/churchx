<?php

use App\AccountTransferStatus;
use App\ExpenseApprovalDecision;
use App\ExpenseStatus;
use App\Models\AccountTransfer;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\GivingType;
use App\Models\Income;
use App\Models\OfferingCollection;
use App\Models\OfferingItem;
use App\Models\Pledge;
use App\Models\Role;
use App\Models\User;
use App\Models\UserRole;
use App\OfferingCollectionStatus;
use App\PledgeStatus;
use App\Services\AccountTransferService;
use App\Services\ExpenseApprovalService;
use App\Services\FinanceReportService;
use App\Services\OfferingPostingService;
use App\Services\PledgePaymentService;
use Database\Seeders\RoleAndPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;

it('posts a verified offering once and audits the operation', function () {
    $user = User::factory()->create();
    $collection = OfferingCollection::factory()->create(['status' => OfferingCollectionStatus::Verified, 'verified_by' => $user->id, 'verified_at' => now()]);
    OfferingItem::factory()->create(['offering_collection_id' => $collection->id]);
    $service = app(OfferingPostingService::class);

    $service->post($collection, $user);
    $service->post($collection->fresh(), $user);

    expect(Income::query()->count())->toBe(1)
        ->and($collection->fresh()->status)->toBe(OfferingCollectionStatus::Posted)
        ->and(AuditLog::query()->where('action', 'offering.posted')->exists())->toBeTrue();
});

it('requires giver information for configured giving types', function () {
    $user = User::factory()->create();
    $collection = OfferingCollection::factory()->create(['status' => OfferingCollectionStatus::Verified]);
    OfferingItem::factory()->create(['offering_collection_id' => $collection->id, 'giving_type_id' => GivingType::factory()->create(['requires_giver' => true])->id]);

    expect(fn () => app(OfferingPostingService::class)->post($collection, $user))->toThrow(DomainException::class);
});

it('allows a scoped finance approver but prevents self approval', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $expense = Expense::factory()->create(['status' => ExpenseStatus::PendingApproval]);
    $approver = User::factory()->create();
    $role = Role::query()->where('name', 'Finance Officer')->firstOrFail();
    UserRole::query()->create(['user_id' => $approver->id, 'role_id' => $role->id, 'branch_id' => $expense->branch_id, 'is_active' => true]);

    $decided = app(ExpenseApprovalService::class)->decide($expense, $approver, ExpenseApprovalDecision::Approved);
    expect($decided->status)->toBe(ExpenseStatus::Approved);

    $own = Expense::factory()->create(['status' => ExpenseStatus::PendingApproval, 'requested_by' => $approver->id, 'branch_id' => $expense->branch_id]);
    expect(fn () => app(ExpenseApprovalService::class)->decide($own, $approver, ExpenseApprovalDecision::Approved))->toThrow(DomainException::class);
});

it('prevents a normal user from approving an expense', function () {
    $expense = Expense::factory()->create(['status' => ExpenseStatus::PendingApproval]);

    expect(fn () => app(ExpenseApprovalService::class)->decide($expense, User::factory()->create(), ExpenseApprovalDecision::Approved))
        ->toThrow(AuthorizationException::class);
});

it('computes pledge paid amount from linked completed income', function () {
    $pledge = Pledge::factory()->create(['pledged_amount' => '100.0000']);
    $income = Income::factory()->create();
    app(PledgePaymentService::class)->link($pledge, $income, '100.0000');

    expect($pledge->fresh()->amount_paid)->toBe('100.0000')->and($pledge->fresh()->status)->toBe(PledgeStatus::Fulfilled);
});

it('completes transfers without creating income or expenses and affects both account balances', function () {
    $this->seed(RoleAndPermissionSeeder::class);
    $user = User::factory()->create();
    UserRole::query()->create(['user_id' => $user->id, 'role_id' => Role::query()->where('name', 'Finance Officer')->firstOrFail()->id, 'branch_id' => null, 'is_active' => true]);
    $from = FinancialAccount::factory()->create(['opening_balance' => '200.0000']);
    $to = FinancialAccount::factory()->create(['opening_balance' => '10.0000']);
    $transfer = AccountTransfer::factory()->create(['from_account_id' => $from->id, 'to_account_id' => $to->id, 'status' => AccountTransferStatus::Approved, 'approved_by' => User::factory()->create()->id]);
    app(AccountTransferService::class)->complete($transfer, $user);

    expect(Income::query()->count())->toBe(0)->and(Expense::query()->count())->toBe(0)
        ->and(app(FinanceReportService::class)->accountBalance($from))->toBe('150.0000')
        ->and(app(FinanceReportService::class)->accountBalance($to))->toBe('60.0000');
});
