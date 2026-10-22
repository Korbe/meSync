<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * PC help and PC builds are priced gross (incl. VAT), web design net (plus VAT).
     * Existing invoices were all calculated net.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('prices_include_tax')->default(false)->after('tax_rate');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('prices_include_tax');
        });
    }
};
