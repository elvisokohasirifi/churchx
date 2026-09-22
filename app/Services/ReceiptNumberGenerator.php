<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ReceiptNumberGenerator
{
    public function next(): string
    {
        return DB::transaction(function (): string {
            $sequence = DB::table('number_sequences')->where('key', 'income_receipt')->lockForUpdate()->first();
            $number = $sequence ? (int) $sequence->next_number : 1;
            DB::table('number_sequences')->updateOrInsert(['key' => 'income_receipt'], ['next_number' => $number + 1, 'created_at' => now(), 'updated_at' => now()]);

            return 'REC-'.now()->format('Ymd').'-'.str_pad((string) $number, 6, '0', STR_PAD_LEFT);
        });
    }
}
