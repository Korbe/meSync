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
        // History of every website check (uptime, response times, outages); pruned after 90 days
        Schema::create('site_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('maintenance_contract_id')->constrained()->cascadeOnDelete();
            $table->timestamp('checked_at');
            $table->boolean('up');
            $table->unsignedInteger('response_ms')->nullable();
            $table->string('error')->nullable();

            $table->index(['maintenance_contract_id', 'checked_at']);
            $table->index('checked_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('site_checks');
    }
};
