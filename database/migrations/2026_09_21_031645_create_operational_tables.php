<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope')->index();
            $table->string('name');
            $table->string('service_type')->index();
            $table->date('date')->index();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
            $table->index(['branch_id', 'date']);
        });
        Schema::create('service_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'branch_id']);
        });
        Schema::create('member_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();
            $table->unique(['service_id', 'member_id']);
        });
        Schema::create('visitor_attendances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('visitor_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'visitor_id']);
        });
        Schema::create('attendance_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('total_male')->default(0);
            $table->unsignedInteger('total_female')->default(0);
            $table->unsignedInteger('total_children')->default(0);
            $table->unsignedInteger('total_members')->default(0);
            $table->unsignedInteger('total_visitors')->default(0);
            $table->foreignUuid('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'branch_id']);
        });
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('scope')->index();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('flyer')->nullable();
            $table->date('start_date')->index();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->string('status')->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('branch_departments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('department_id')->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->unique(['branch_id', 'department_id']);
        });
        Schema::create('department_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('branch_department_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_department_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('department_role_id')->constrained()->restrictOnDelete();
            $table->date('joined_date')->nullable();
            $table->date('left_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['branch_department_id', 'member_id', 'is_active'], 'bdm_branch_member_active_idx');
        });
        Schema::create('church_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('type')->index();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('group_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('church_groups')->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->date('joined_date')->nullable();
            $table->date('left_date')->nullable();
            $table->string('role')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['group_id', 'member_id', 'is_active']);
        });
    }

    public function down(): void
    {
        foreach (['group_members', 'church_groups', 'branch_department_members', 'department_roles', 'branch_departments', 'departments', 'events', 'attendance_summaries', 'visitor_attendances', 'member_attendances', 'service_branches', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
