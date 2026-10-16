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
        // A cancellation invoice (Stornorechnung) points to the invoice it cancels
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('cancels_invoice_id')->nullable()->unique()->after('maintenance_contract_id')
                ->constrained('invoices')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cancels_invoice_id');
        });
    }
};
