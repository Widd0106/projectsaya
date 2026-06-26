<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel 'chats' merepresentasikan satu sesi/thread percakapan
        // antara satu user dan satu character. Diperlukan supaya
        // "Recent Chats" di sidebar bisa menampilkan preview pesan
        // terakhir + timestamp tanpa query rumit ke tabel messages.
        Schema::create('chats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('character_id')->constrained()->onDelete('cascade');
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'character_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chats');
    }
};
