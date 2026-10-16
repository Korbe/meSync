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
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            // Uptime monitoring of the contract URL
            $table->boolean('monitor_enabled')->default(true)->after('notes');
            $table->string('monitor_status', 10)->nullable()->after('monitor_enabled');          // up | down
            $table->timestamp('monitor_status_since')->nullable()->after('monitor_status');
            $table->timestamp('monitor_checked_at')->nullable()->after('monitor_status_since');
            $table->unsignedTinyInteger('monitor_failures')->default(0)->after('monitor_checked_at');
            $table->unsignedInteger('monitor_response_ms')->nullable()->after('monitor_failures');
            $table->string('monitor_error')->nullable()->after('monitor_response_ms');

            // SSL certificate expiry (Let's Encrypt renews 30 days ahead, so < 14 days means renewal failed)
            $table->timestamp('ssl_expires_at')->nullable()->after('monitor_error');
            $table->date('ssl_warning_sent_for')->nullable()->after('ssl_expires_at');

            // Due date for which the "invoice due in 7 days" reminder was already sent
            $table->date('reminder_sent_for')->nullable()->after('ssl_warning_sent_for');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn([
                'monitor_enabled', 'monitor_status', 'monitor_status_since', 'monitor_checked_at', 'monitor_failures',
                'monitor_response_ms', 'monitor_error', 'ssl_expires_at', 'ssl_warning_sent_for', 'reminder_sent_for',
            ]);
        });
    }
};
