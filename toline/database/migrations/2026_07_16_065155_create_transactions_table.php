<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('invoice_number')->unique();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('subtotal', 15, 2);
            $table->decimal('service_fee', 15, 2)->default(1500);
            $table->decimal('admin_fee', 15, 2)->default(2000);
            $table->decimal('total', 15, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'completed'])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};