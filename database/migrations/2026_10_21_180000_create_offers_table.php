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
        // Offers for websites and web apps; the maintenance (first 12 months) is always part of them
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();

            $table->string('type', 20);                        // website | webapp
            $table->string('number', 50);
            $table->string('title');                           // Projekt, wird zum Namen des Wartungsvertrags
            $table->string('url')->nullable();
            $table->date('offer_date');
            $table->date('valid_until');
            $table->decimal('tax_rate', 5, 2)->default(20);

            // Scope as in config/offers.php
            $table->string('package', 30)->nullable();
            $table->json('features');
            $table->json('integrations');
            $table->string('other_function')->nullable();
            $table->string('content_delivery', 20)->nullable();
            $table->string('payment_terms', 20);
            $table->boolean('reference_customer')->default(false);

            $table->string('maintenance_package', 50)->nullable();
            $table->decimal('maintenance_monthly_price', 8, 2);

            $table->text('intro')->nullable();
            $table->text('notes')->nullable();

            // Snapshots, so a sent offer never changes when customer or sender data changes later
            $table->json('recipient');
            $table->json('issuer');

            $table->timestamp('sent_at')->nullable();
            $table->string('sent_to')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();

            // Created on acceptance and when billing it
            $table->foreignId('maintenance_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();

            $table->timestamps();

            $table->unique(['user_id', 'number']);
            $table->index(['user_id', 'offer_date']);
        });

        Schema::create('offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->text('description');
            $table->decimal('quantity', 10, 2);
            $table->string('unit', 30)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('list_price', 10, 2)->nullable();   // regular price, shown struck through when higher
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offer_items');
        Schema::dropIfExists('offers');
    }
};
