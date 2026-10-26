<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unsubscribed through the link in a newsletter: gets no further newsletters
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('newsletter_opt_out_at')->nullable()->after('interests');
        });

        Schema::create('newsletters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('subject');
            $table->text('body');
            $table->string('audience', 20)->default('all');   // all | interests | selected
            $table->json('interests')->nullable();            // App\Enums\CustomerInterest values
            $table->json('customer_ids')->nullable();         // audience "selected"
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // Who got it: fixed when sending, also if the customer changes or is deleted later
        Schema::create('newsletter_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('newsletter_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_recipients');
        Schema::dropIfExists('newsletters');

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('newsletter_opt_out_at');
        });
    }
};
