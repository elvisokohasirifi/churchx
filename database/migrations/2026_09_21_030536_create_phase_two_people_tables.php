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
        Schema::create('number_sequences', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->unsignedBigInteger('next_number')->default(1);
            $table->timestamps();
        });

        Schema::create('leadership_titles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('membership_number')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->string('phone')->nullable()->index();
            $table->string('alternative_phone')->nullable();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('gender')->nullable()->index();
            $table->string('marital_status')->nullable()->index();
            $table->string('occupation')->nullable();
            $table->string('highest_education')->nullable();
            $table->string('profile_photo')->nullable();
            $table->date('date_joined')->nullable();
            $table->string('membership_status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('member_id')->references('id')->on('members')->nullOnDelete();
        });

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('started_by_member_id')->references('id')->on('members')->nullOnDelete();
        });

        Schema::create('branch_leaders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('leadership_title_id')->constrained()->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->index(['branch_id', 'is_active']);
        });

        Schema::create('member_branches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->date('joined_date');
            $table->date('left_date')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->string('status')->index();
            $table->timestamps();
            $table->index(['branch_id', 'status']);
            $table->unique(['member_id', 'branch_id', 'joined_date']);
        });

        if (in_array(DB::getDriverName(), ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX member_branches_active_primary_unique ON member_branches (member_id) WHERE is_primary = true AND left_date IS NULL');
        }

        Schema::create('household_relationships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('households', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('family_name');
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('household_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('household_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('member_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('relationship_id')->constrained('household_relationships')->restrictOnDelete();
            $table->boolean('is_head')->default(false);
            $table->timestamps();
            $table->unique(['household_id', 'member_id']);
        });

        Schema::create('visitors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('phone')->nullable()->index();
            $table->string('email')->nullable()->index();
            $table->text('address')->nullable();
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->foreignUuid('invited_by_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->date('first_visit_date')->index();
            $table->text('notes')->nullable();
            $table->foreignUuid('converted_to_member_id')->nullable()->unique()->constrained('members')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
        Schema::dropIfExists('household_members');
        Schema::dropIfExists('households');
        Schema::dropIfExists('household_relationships');
        Schema::dropIfExists('member_branches');
        Schema::dropIfExists('branch_leaders');
        Schema::table('branches', fn (Blueprint $table) => $table->dropForeign(['started_by_member_id']));
        Schema::table('users', fn (Blueprint $table) => $table->dropForeign(['member_id']));
        Schema::dropIfExists('members');
        Schema::dropIfExists('leadership_titles');
        Schema::dropIfExists('number_sequences');
    }
};
