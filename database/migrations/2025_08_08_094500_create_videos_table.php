<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $t) {
            $t->id();
            $t->string('hash', 64)->unique(); // SHA-256 hex
            $t->string('ext', 8)->nullable();
            $t->unsignedBigInteger('bytes')->nullable();
            $t->string('path'); // storage relative path
            $t->json('meta')->nullable(); // optional: duration, codec
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};