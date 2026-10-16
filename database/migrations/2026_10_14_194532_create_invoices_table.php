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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('maintenance_contract_id')->nullable()->constrained()->nullOnDelete();

            $table->string('number', 50);
            $table->date('invoice_date');
            $table->date('due_date');
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->text('notes')->nullable();

            // Snapshots, so an invoice never changes when customer or sender data changes later
            $table->json('recipient');
            $table->json('issuer');

            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'invoice_date']);
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
