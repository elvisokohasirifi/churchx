<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->foreignUuid('branch_leader_id')
                ->nullable()
                ->after('shepherd_id')
                ->constrained('branch_leaders')
                ->restrictOnDelete();
        });

        $today = now()->toDateString();
        $singleLeaderBranches = DB::table('branch_leaders')
            ->select('branch_id', DB::raw('MIN(id) as branch_leader_id'))
            ->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->where(fn ($query) => $query->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->groupBy('branch_id')
            ->havingRaw('COUNT(*) = 1')
            ->get();

        foreach ($singleLeaderBranches as $assignment) {
            DB::table('members')
                ->whereNull('branch_leader_id')
                ->whereIn('id', DB::table('member_branches')
                    ->select('member_id')
                    ->where('branch_id', $assignment->branch_id)
                    ->where('is_primary', true)
                    ->whereNull('left_date'))
                ->update(['branch_leader_id' => $assignment->branch_leader_id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_leader_id');
        });
    }
};
