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
        // Own order of the websites in the monitoring center; null = by creation
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->unsignedInteger('monitor_position')->nullable()->after('monitor_enabled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn('monitor_position');
        });
    }
};
