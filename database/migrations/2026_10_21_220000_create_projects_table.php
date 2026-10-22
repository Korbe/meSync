<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Projects (websites, web apps) bundle offers, invoices and the maintenance contract.
     * Their phase follows from those, so it isn't stored.
     */
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);                        // website | webapp
            $table->string('name');
            $table->string('url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        foreach (['offers', 'invoices', 'maintenance_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('project_id')->nullable()->after('customer_id')->constrained()->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['offers', 'invoices', 'maintenance_contracts'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('project_id');
            });
        }

        Schema::dropIfExists('projects');
    }
};
