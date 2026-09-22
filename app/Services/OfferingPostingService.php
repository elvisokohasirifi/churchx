<?php

namespace App\Services;

use App\IncomeStatus;
use App\Models\Income;
use App\Models\OfferingCollection;
use App\Models\User;
use App\OfferingCollectionStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class OfferingPostingService
{
    public function __construct(private ReceiptNumberGenerator $receipts, private AuditLogService $audit) {}

    public function post(OfferingCollection $collection, User $user): OfferingCollection
    {
        return DB::transaction(function () use ($collection, $user): OfferingCollection {
            $collection = OfferingCollection::query()->with('items.givingType')->lockForUpdate()->findOrFail($collection->id);
            if ($collection->status === OfferingCollectionStatus::Posted) {
                return $collection;
            }
            if ($collection->status !== OfferingCollectionStatus::Verified) {
                throw new DomainException('Only verified offering collections can be posted.');
            }
            foreach ($collection->items as $item) {
                if ($item->income_id !== null) {
                    throw new DomainException('This offering item is already linked to income.');
                }
                if ($item->givingType->requires_giver && $item->member_id === null && blank($item->giver_name)) {
                    throw new DomainException('A giver is required for '.$item->givingType->name.'.');
                }
                $income = Income::query()->create([
                    'branch_id' => $collection->branch_id, 'service_id' => $collection->service_id,
                    'giving_type_id' => $item->giving_type_id, 'fund_id' => $item->fund_id,
                    'payment_method_id' => $item->payment_method_id, 'financial_account_id' => $item->financial_account_id,
                    'giver_member_id' => $item->member_id, 'giver_name' => $item->giver_name, 'giver_phone' => $item->giver_phone,
                    'amount' => $item->amount, 'currency' => $item->currency, 'date' => $collection->date,
                    'recorded_by' => $user->id, 'receipt_number' => $this->receipts->next(), 'status' => IncomeStatus::Completed,
                    'source_type' => OfferingCollection::class, 'source_id' => $collection->id,
                ]);
                $item->update(['income_id' => $income->id]);
            }
            $collection->update(['status' => OfferingCollectionStatus::Posted]);
            $this->audit->record('offering.posted', $user, $collection, context: ['item_count' => $collection->items->count()]);

            return $collection->refresh();
        });
    }
}
