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
            $table->boolean('is_shepherd')->default(false)->index()->after('shepherd_id');
        });

        DB::table('members')
            ->whereNotNull('shepherd_id')
            ->pluck('shepherd_id')
            ->unique()
            ->chunk(500)
            ->each(fn ($shepherdIds) => DB::table('members')->whereIn('id', $shepherdIds)->update(['is_shepherd' => true]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropColumn('is_shepherd');
        });
    }
};
