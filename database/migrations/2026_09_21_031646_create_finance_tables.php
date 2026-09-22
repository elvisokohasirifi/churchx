<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('funds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('restricted')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('giving_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('requires_giver')->default(false);
            $table->foreignUuid('default_fund_id')->nullable()->constrained('funds')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('type')->index();
            $table->string('currency', 3);
            $table->string('account_number')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('opening_balance', 19, 4)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('incomes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('service_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('giving_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fund_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('giver_member_id')->nullable()->constrained('members')->nullOnDelete();
            $table->string('giver_name')->nullable();
            $table->string('giver_phone')->nullable();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->string('transaction_reference')->nullable()->index();
            $table->date('date')->index();
            $table->text('notes')->nullable();
            $table->foreignUuid('recorded_by')->constrained('users')->restrictOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('status')->index();
            $table->string('source_type')->nullable();
            $table->uuid('source_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['financial_account_id', 'status', 'date']);
            $table->index(['source_type', 'source_id']);
        });
        Schema::create('offering_collections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->foreignUuid('counted_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('offering_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('offering_collection_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('giving_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fund_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('member_id')->nullable()->constrained()->nullOnDelete();
            $table->string('giver_name')->nullable();
            $table->string('giver_phone')->nullable();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->foreignUuid('income_id')->nullable()->unique()->constrained('incomes')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('expense_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('expense_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fund_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('payment_method_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('financial_account_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('disbursed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_contact')->nullable();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->text('description');
            $table->string('transaction_reference')->nullable()->index();
            $table->date('date')->index();
            $table->string('receipt')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['branch_id', 'status', 'date']);
        });
        Schema::create('expense_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('expense_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('approver_id')->constrained('users')->restrictOnDelete();
            $table->string('decision');
            $table->text('comments')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['expense_id', 'approver_id']);
        });
        Schema::create('pledges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('member_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('giving_type_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('fund_id')->constrained()->restrictOnDelete();
            $table->decimal('pledged_amount', 19, 4);
            $table->date('due_date')->nullable()->index();
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('pledge_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('pledge_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('income_id')->unique()->constrained()->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->timestamps();
        });
        Schema::create('account_transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('from_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignUuid('to_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->decimal('amount', 19, 4);
            $table->string('currency', 3);
            $table->string('transaction_reference')->nullable()->index();
            $table->date('date')->index();
            $table->foreignUuid('initiated_by')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('status')->index();
            $table->timestamps();
        });
        Schema::create('asset_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        Schema::create('assets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('branch_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->foreignUuid('asset_type_id')->constrained()->restrictOnDelete();
            $table->date('purchase_date')->nullable();
            $table->decimal('purchase_price', 19, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('condition')->nullable();
            $table->string('serial_number')->nullable()->index();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        foreach (['assets', 'asset_types', 'account_transfers', 'pledge_payments', 'pledges', 'expense_approvals', 'expenses', 'expense_types', 'offering_items', 'offering_collections', 'incomes', 'financial_accounts', 'giving_types', 'funds', 'payment_methods'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
