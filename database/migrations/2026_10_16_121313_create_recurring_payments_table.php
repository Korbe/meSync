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
        // A contract payment confirmed against the bank statement, with the amount actually debited
        Schema::create('recurring_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recurring_expense_id')->constrained()->cascadeOnDelete();
            $table->date('period');                       // first day of the month
            $table->decimal('amount', 10, 2);             // full amount as on the statement (before own share)
            $table->timestamp('confirmed_at');
            $table->timestamps();

            $table->unique(['recurring_expense_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('recurring_payments');
    }
};
