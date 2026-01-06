<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('author')->nullable();
            $table->string('isbn')->nullable();
            $table->string('year')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('classification')->nullable(); // 1-5 podle stavu knihy
            $table->string('bin_number'); // Číslo přepravky
            $table->json('photos')->nullable(); // Cesty k fotkám
            $table->string('status')->default('new'); // new, review, done
            $table->text('ocr_full_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
