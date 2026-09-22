<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('status')->default('upcoming')->index()->after('location');
        });

        Schema::table('member_attendances', function (Blueprint $table) {
            $table->string('status')->default('present')->index()->after('member_id');
        });

        Schema::table('branch_department_members', function (Blueprint $table) {
            $table->unique(['branch_department_id', 'member_id'], 'bdm_department_member_unique');
        });

        Schema::table('group_members', function (Blueprint $table) {
            $table->unique(['group_id', 'member_id'], 'group_member_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('group_members', function (Blueprint $table) {
            $table->dropUnique('group_member_unique');
        });

        Schema::table('branch_department_members', function (Blueprint $table) {
            $table->dropUnique('bdm_department_member_unique');
        });

        Schema::table('member_attendances', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
