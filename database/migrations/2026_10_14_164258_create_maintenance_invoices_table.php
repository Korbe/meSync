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
        Schema::create('maintenance_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();

            $table->date('invoiced_at');                       // wann die Rechnung verschickt wurde
            $table->date('period_start');                      // verrechneter Zeitraum
            $table->date('period_end');
            $table->decimal('amount', 10, 2);
            $table->string('invoice_number', 50)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_invoices');
    }
};
