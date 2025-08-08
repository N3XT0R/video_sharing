<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('email')->unique();
            $t->unsignedInteger('weight')->default(1); // für gewichtete RR
            $t->unsignedInteger('weekly_quota')->default(5);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};