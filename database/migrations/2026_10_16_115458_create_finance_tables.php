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
        // Contracts and other fixed costs/income that repeat (rent, insurance, subscriptions, salary)
        Schema::create('recurring_expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                  // e.g. "Spotify Duo"
            $table->string('type', 10)->default('expense');          // expense | income
            $table->string('scope', 10);                             // private | business
            $table->string('category', 30);                          // App\Enums\FinanceCategory
            $table->decimal('amount', 10, 2);                        // per payment, full price
            $table->unsignedTinyInteger('interval_months')->default(1); // 1, 3, 6, 12
            $table->unsignedTinyInteger('share_percent')->default(100); // own share for shared contracts
            $table->date('start_date');                              // first payment, sets the due months
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'scope']);
        });

        // Single bookings: variable expenses and income, typed in as they happen
        Schema::create('finance_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->string('description');                           // e.g. "Hofer", "Tanken"
            $table->string('type', 10)->default('expense');          // expense | income
            $table->string('scope', 10);                             // private | business
            $table->string('category', 30);
            $table->decimal('amount', 10, 2);
            $table->string('amount_input')->nullable();              // as typed, e.g. "5,92+6,5"
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finance_entries');
        Schema::dropIfExists('recurring_expenses');
    }
};
