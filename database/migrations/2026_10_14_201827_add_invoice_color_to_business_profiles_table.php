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
        // Accent color of the user's invoices (hex, e.g. "#1fadae")
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('invoice_color', 7)->default('#1fadae')->after('default_tax_rate');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn('invoice_color');
        });
    }
};
