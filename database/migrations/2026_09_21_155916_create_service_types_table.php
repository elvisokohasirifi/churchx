<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('service_types', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        $timestamp = now();
        DB::table('services')->whereNotNull('service_type')->distinct()->pluck('service_type')->each(
            fn (string $name) => DB::table('service_types')->insert([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'is_active' => true,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_types');
    }
};
