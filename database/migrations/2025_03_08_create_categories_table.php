<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('category_word', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->foreignId('amharic_word_id')->constrained()->onDelete('cascade');
            $table->integer('level')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('category_word');
        Schema::dropIfExists('categories');
    }
};
