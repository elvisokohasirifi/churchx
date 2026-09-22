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
        Schema::create('churches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('logo')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();
            $table->string('country')->nullable();
            $table->string('currency', 3);
            $table->string('timezone');
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('role_permissions', function (Blueprint $table) {
            $table->foreignUuid('role_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('address')->nullable();
            $table->string('location')->nullable();
            $table->string('gps_coordinates')->nullable();
            $table->date('date_started')->nullable();
            $table->string('status')->index();
            $table->uuid('started_by_member_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('role_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUuid('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();
            $table->unique(['user_id', 'role_id', 'branch_id']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX user_roles_church_wide_unique ON user_roles (user_id, role_id) WHERE branch_id IS NULL');
        }

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX user_roles_church_wide_unique ON user_roles (user_id, role_id) WHERE branch_id IS NULL');
        }

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action')->index();
            $table->nullableUuidMorphs('auditable');
            $table->json('changes')->nullable();
            $table->json('context')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('churches');
    }
};
