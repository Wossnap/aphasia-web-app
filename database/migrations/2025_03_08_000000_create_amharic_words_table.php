<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('amharic_words', function (Blueprint $table) {
            $table->id();
            $table->string('word');
            $table->json('transliterations')->nullable(); // Array of accepted pronunciations
            $table->string('meaning')->nullable(); // English meaning
            $table->string('audio_path')->nullable(); // Path to audio file
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('amharic_words');
    }
};
