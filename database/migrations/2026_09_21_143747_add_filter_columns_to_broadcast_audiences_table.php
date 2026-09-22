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
        Schema::table('broadcast_audiences', function (Blueprint $table) {
            $table->string('filter_field')->nullable();
            $table->string('filter_operator')->nullable();
            $table->text('filter_value')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('broadcast_audiences', function (Blueprint $table) {
            $table->dropColumn(['filter_field', 'filter_operator', 'filter_value']);
        });
    }
};
