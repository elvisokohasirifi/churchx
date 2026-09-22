<?php

namespace App\Services;

use App\AccountTransferStatus;
use App\Models\AccountTransfer;
use App\Models\FinancialAccount;
use App\Models\User;
use App\PermissionCode;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class AccountTransferService
{
    public function __construct(private AuditLogService $audit, private BranchAccessService $access) {}

    public function approve(AccountTransfer $transfer, User $user): AccountTransfer
    {
        $this->authorize($transfer, $user);
        if ($transfer->initiated_by === $user->id) {
            throw new DomainException('Initiators cannot approve their own transfers.');
        }
        if ($transfer->status !== AccountTransferStatus::PendingApproval) {
            throw new DomainException('Only pending transfers can be approved.');
        }
        $transfer->update(['status' => AccountTransferStatus::Approved, 'approved_by' => $user->id, 'approved_at' => now()]);
        $this->audit->record('account_transfer.approved', $user, $transfer);

        return $transfer->refresh();
    }

    public function complete(AccountTransfer $transfer, User $user): AccountTransfer
    {
        $this->authorize($transfer, $user);

        return DB::transaction(function () use ($transfer, $user): AccountTransfer {
            $transfer = AccountTransfer::query()->lockForUpdate()->findOrFail($transfer->id);
            if ($transfer->status === AccountTransferStatus::Completed) {
                return $transfer;
            }
            if ($transfer->status !== AccountTransferStatus::Approved) {
                throw new DomainException('Only approved transfers can be completed.');
            }
            if ($transfer->from_account_id === $transfer->to_account_id) {
                throw new DomainException('Transfer accounts must differ.');
            }
            $accounts = FinancialAccount::query()->whereKey([$transfer->from_account_id, $transfer->to_account_id])->lockForUpdate()->get()->keyBy('id');
            if ($accounts->count() !== 2 || $accounts[$transfer->from_account_id]->currency !== $transfer->currency || $accounts[$transfer->to_account_id]->currency !== $transfer->currency) {
                throw new DomainException('Transfer currency must match both accounts.');
            }
            $transfer->update(['status' => AccountTransferStatus::Completed]);
            $this->audit->record('account_transfer.completed', $user, $transfer);

            return $transfer->refresh();
        });
    }

    private function authorize(AccountTransfer $transfer, User $user): void
    {
        $accounts = FinancialAccount::query()->whereKey([$transfer->from_account_id, $transfer->to_account_id])->get();
        if ($accounts->count() !== 2 || $accounts->contains(fn (FinancialAccount $account): bool => ! $this->access->allows($user, PermissionCode::TransfersApprove, $account->branch_id))) {
            throw new AuthorizationException;
        }
    }
}
