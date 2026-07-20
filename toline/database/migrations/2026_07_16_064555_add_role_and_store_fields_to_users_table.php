<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['buyer', 'seller'])->default('buyer')->after('email');
            $table->string('store_name')->nullable()->after('role');
            $table->string('store_avatar')->nullable()->after('store_name');
            $table->text('store_description')->nullable()->after('store_avatar');
            $table->decimal('balance', 15, 2)->default(0)->after('store_description');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'store_name', 'store_avatar', 'store_description', 'balance']);
        });
    }
};