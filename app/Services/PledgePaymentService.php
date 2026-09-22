<?php

namespace App\Services;

use App\IncomeStatus;
use App\Models\Income;
use App\Models\Pledge;
use App\Models\PledgePayment;
use App\PledgeStatus;
use DomainException;
use Illuminate\Support\Facades\DB;

class PledgePaymentService
{
    public function link(Pledge $pledge, Income $income, string $amount): PledgePayment
    {
        if ($income->status !== IncomeStatus::Completed) {
            throw new DomainException('Only completed income can pay a pledge.');
        }

        return DB::transaction(function () use ($pledge, $income, $amount): PledgePayment {
            $payment = PledgePayment::query()->create(['pledge_id' => $pledge->id, 'income_id' => $income->id, 'amount' => $amount]);
            $pledge->refresh();
            if (bccomp($pledge->amount_paid, (string) $pledge->pledged_amount, 4) >= 0) {
                $pledge->update(['status' => PledgeStatus::Fulfilled]);
            }

            return $payment;
        });
    }
}
