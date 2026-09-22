<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class MembershipNumberGenerator
{
    public function generate(Branch $branch): string
    {
        return DB::transaction(function () use ($branch): string {
            $key = 'membership:'.strtolower($branch->code);

            DB::table('number_sequences')->insertOrIgnore([
                'key' => $key,
                'next_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = DB::table('number_sequences')->where('key', $key)->lockForUpdate()->first();
            $number = (int) $sequence->next_number;
            DB::table('number_sequences')->where('key', $key)->update([
                'next_number' => $number + 1,
                'updated_at' => now(),
            ]);

            return sprintf('MEM-%s-%06d', strtoupper($branch->code), $number);
        });
    }
}
