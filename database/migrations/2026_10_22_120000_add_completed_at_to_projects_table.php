<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When the actual work of an accepted project was finished: only then it is invoiced.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->date('completed_at')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};
