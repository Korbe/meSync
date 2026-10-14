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
        Schema::create('maintenance_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();

            $table->string('type', 20);                        // website | webapp
            $table->string('name');                            // Projekt, z. B. "korbitsch.at"
            $table->string('url')->nullable();
            $table->decimal('monthly_price', 8, 2);

            $table->date('start_date');
            $table->date('next_invoice_date');                 // Jahresrechnung wird im Voraus gestellt
            $table->date('end_date')->nullable();              // gekündigt zum

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'next_invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_contracts');
    }
};
