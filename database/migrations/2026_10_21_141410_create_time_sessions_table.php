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
        // Stopwatch for on-site work (PC help): runs on the server, so it survives closing the app
        Schema::create('time_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('catalog_item_id')->nullable()->constrained()->nullOnDelete();   // time rate
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();        // set once billed
            $table->timestamp('started_at');
            $table->timestamp('running_since')->nullable();     // null = paused or stopped
            $table->unsignedInteger('seconds')->default(0);     // time of the finished running phases
            $table->timestamp('stopped_at')->nullable();
            $table->unsignedSmallInteger('billed_minutes')->nullable();   // corrected duration, else the measured one
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'stopped_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('time_sessions');
    }
};
