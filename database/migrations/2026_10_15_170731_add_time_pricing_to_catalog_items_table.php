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
        // Time-based rates, e.g. "50 € for the first 30 minutes, then 20 € per started 15 minutes".
        // For time pricing, unit_price is the base price for the first base_minutes.
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->string('pricing', 10)->default('fixed')->after('unit_price');   // fixed | time
            $table->unsignedSmallInteger('base_minutes')->nullable()->after('pricing');
            $table->unsignedSmallInteger('step_minutes')->nullable()->after('base_minutes');
            $table->decimal('step_price', 10, 2)->nullable()->after('step_minutes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catalog_items', function (Blueprint $table) {
            $table->dropColumn(['pricing', 'base_minutes', 'step_minutes', 'step_price']);
        });
    }
};
