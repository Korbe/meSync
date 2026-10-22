<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ordered without an offer in meSync (own contract, by phone, by mail): straight to the implementation.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('ordered_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('ordered_at');
        });
    }
};
