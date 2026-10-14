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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Person
            $table->string('title_prefix', 50)->nullable();   // vorangestellter Titel, z. B. "Dr.", "Mag."
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('title_suffix', 50)->nullable();   // nachgestellter Titel, z. B. "BSc", "MBA"

            // Firma
            $table->string('company_name')->nullable();
            $table->string('company_suffix', 50)->nullable(); // Rechtsform / Firmenzusatz, z. B. "GmbH", "e.U."

            // Adresse
            $table->string('street')->nullable();
            $table->string('zip', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country')->nullable();

            // Kontakt
            $table->string('email')->nullable();
            $table->string('phone', 50)->nullable();

            // Steuer
            $table->string('uid_number', 20)->nullable();     // UID / ATU-Nummer

            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'last_name']);
            $table->index(['user_id', 'company_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
