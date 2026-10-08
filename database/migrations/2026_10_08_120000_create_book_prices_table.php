<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('book_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 10, 2);
            $table->string('currency', 3)->default('CZK');
            $table->string('source');
            $table->string('url', 2048)->nullable();
            $table->string('title')->nullable();
            $table->string('condition')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_prices');
    }
};
