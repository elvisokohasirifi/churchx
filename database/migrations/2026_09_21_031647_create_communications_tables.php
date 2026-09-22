<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->text('message');
            $table->string('channel')->index();
            $table->foreignUuid('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->index();
            $table->timestamps();
        });
        Schema::create('broadcast_audiences', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('broadcast_id')->constrained()->cascadeOnDelete();
            $table->string('audience_type')->index();
            $table->uuid('audience_id')->nullable();
            $table->timestamps();
            $table->index(['audience_type', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_audiences');
        Schema::dropIfExists('broadcasts');
    }
};
