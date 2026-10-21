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
        // Second mail on the due date itself ("heute fällig"), once per due date
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->date('due_notice_sent_for')->nullable()->after('reminder_sent_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn('due_notice_sent_for');
        });
    }
};
