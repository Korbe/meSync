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
        // Frequently used services/products that can be inserted into invoices
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                    // e.g. "PC-Hilfe vor Ort"
            $table->text('description')->nullable();   // printed small below the name on the invoice
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
