<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use App\Enums\PrimaryEmotion;
use App\Enums\SecondaryEmotion;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('emotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->tinyInteger('score')->unsigned()->check('score BETWEEN 1 AND 100');
            $table->enum('primary', array_column(PrimaryEmotion::cases(), 'value'));
            $table->enum('secondary', array_column(SecondaryEmotion::cases(), 'value'));
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emotions');
    }
};
