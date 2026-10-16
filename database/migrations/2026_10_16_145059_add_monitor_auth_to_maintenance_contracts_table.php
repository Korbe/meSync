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
        // Websites behind a password (htpasswd / HTTP Basic Auth)
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->string('monitor_auth', 10)->default('none')->after('monitor_position');   // none | basic | accept_401
            $table->string('monitor_auth_user')->nullable()->after('monitor_auth');
            $table->text('monitor_auth_password')->nullable()->after('monitor_auth_user');   // encrypted with APP_KEY
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('maintenance_contracts', function (Blueprint $table) {
            $table->dropColumn(['monitor_auth', 'monitor_auth_user', 'monitor_auth_password']);
        });
    }
};
