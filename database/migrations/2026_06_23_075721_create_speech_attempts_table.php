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
        Schema::create('speech_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('amharic_word_id')->nullable()->constrained('amharic_words')->nullOnDelete();
            $table->string('transcription')->nullable();          // what the speech API returned
            $table->json('checked_transliterations')->nullable(); // the word's transliterations at the time of the attempt
            $table->string('audio_path')->nullable();             // recorded audio file for playback
            $table->boolean('is_correct')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('speech_attempts');
    }
};
