<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('characters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Basic Info (sesuai form "Basic Info" di Figma)
            $table->string('name');
            $table->string('gender')->nullable();
            $table->text('greeting');

            // Avatar (sesuai "Character Avatar" di Figma)
            $table->string('avatar_path')->nullable();

            // Personality & Backstory (sesuai panel kanan form Figma)
            $table->string('short_description');
            $table->text('long_description')->nullable();

            // Advanced Configuration -> contoh dialog (json array of {user, ai})
            $table->json('example_dialogues')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('characters');
    }
};
