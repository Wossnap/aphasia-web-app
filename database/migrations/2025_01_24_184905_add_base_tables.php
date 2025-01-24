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
        Schema::create('amharic_letters', function (Blueprint $table) {
            $table->id();
            $table->string('letter')->unique(); // Specific letter, e.g., ሀ, ሁ, ሂ
            $table->unsignedBigInteger('group_id'); // Group reference (e.g., ሀ group, ለ group)
            $table->integer('position'); // Position in the group, e.g., 1 for ሀ, 2 for ሁ
            $table->json('transliterations'); // JSON array of transliterations, e.g., ["hä", "ha"]
            $table->timestamps();
        });

        Schema::create('amharic_letter_groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_letter')->unique(); // Base letter representing the group, e.g., ሀ
            $table->timestamps();
        });

        Schema::create('levels', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // Level name, e.g., "Level 1"
            $table->json('pattern'); // JSON pattern for this level, e.g., [1, 2, 1, 3]
            $table->timestamps();
        });

        Schema::create('user_progress', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id'); // User making progress
            $table->unsignedBigInteger('amharic_letter_id'); // Reference to specific letter
            $table->unsignedBigInteger('level_id'); // Reference to the current level
            $table->integer('attempts')->default(0); // Total attempts made
            $table->integer('correct_attempts')->default(0); // Count of correct attempts
            $table->integer('incorrect_attempts')->default(0); // Count of incorrect attempts
            $table->float('average_time')->nullable(); // Average time taken for correct attempts
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
