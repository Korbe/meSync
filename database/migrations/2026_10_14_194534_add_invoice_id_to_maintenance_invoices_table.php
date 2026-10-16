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
        // Link a maintenance billing entry to the invoice it was created with
        Schema::table('maintenance_invoices', function (Blueprint $table) {
            $table->foreignId('invoice_id')->nullable()->after('maintenance_contract_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_id');
        });
    }
};
