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
        // Purchased devices and equipment (Anlagenverzeichnis) with their value
        Schema::create('inventory_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');                                   // e.g. "MacBook Pro 14"
            $table->string('category', 30);                           // App\Enums\InventoryCategory
            $table->string('serial_number')->nullable();
            $table->string('supplier')->nullable();                   // where it was bought
            $table->date('purchased_at');
            $table->decimal('price', 10, 2);                          // purchase price, net
            $table->decimal('tax_rate', 5, 2)->default(20);
            $table->unsignedTinyInteger('useful_life_years')->nullable(); // depreciation period, null for GWG
            $table->string('status', 10)->default('active');          // active | sold | disposed
            $table->date('retired_at')->nullable();                   // sold or disposed on
            $table->decimal('sale_price', 10, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'purchased_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inventory_items');
    }
};
