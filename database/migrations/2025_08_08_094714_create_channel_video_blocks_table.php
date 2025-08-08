<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channel_video_blocks', function (Blueprint $t) {
            $t->id();
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('video_id')->constrained()->cascadeOnDelete();
            $t->timestamp('until');
            $t->timestamps();
            $t->unique(['channel_id', 'video_id']);
            $t->index('until');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channel_video_blocks');
    }
};