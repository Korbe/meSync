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
        // Paid in cash when the invoice was created: only then the PDF doubles as a receipt.
        // Later payments never change the PDF that was sent to the customer.
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('paid_in_cash')->default(false)->after('paid_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('paid_in_cash');
        });
    }
};
