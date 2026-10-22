<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A contract from an offer waits for the go-live: no start and no due date yet.
     */
    public function up(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->date('start_date')->nullable()->change();
            $table->date('next_invoice_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->date('start_date')->nullable(false)->change();
            $table->date('next_invoice_date')->nullable(false)->change();
        });
    }
};
