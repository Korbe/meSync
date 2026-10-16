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
        // Sender / bank details printed on the user's invoices
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('name')->nullable();                // z. B. "Lukas Korbitsch"
            $table->string('street')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('website')->nullable();

            $table->string('uid_number', 20)->nullable();

            $table->string('bank_name')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('iban', 34)->nullable();
            $table->string('bic', 11)->nullable();

            $table->unsignedSmallInteger('payment_terms_days')->default(14);
            $table->decimal('default_tax_rate', 5, 2)->default(20);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
