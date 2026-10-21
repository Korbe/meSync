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
        // Business designation for customer mails (e.g. "Korbitsch Web Software und IT Dienstleistungen");
        // the invoices keep the legal name
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn('brand_name');
        });
    }
};
