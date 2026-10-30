<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The PDF is rendered on every download: an offer keeps the conditions it went out with
        Schema::table('offers', function (Blueprint $table) {
            $table->unsignedTinyInteger('terms_version')->default(2)->after('payment_terms');
        });

        // Already with the customer: no minimum term and renewal clause afterwards
        DB::table('offers')->whereNotNull('sent_at')->update(['terms_version' => 1]);
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn('terms_version');
        });
    }
};
