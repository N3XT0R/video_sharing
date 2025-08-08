<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('video_id')->constrained()->cascadeOnDelete();
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->foreignId('batch_id')->constrained('batches')->cascadeOnDelete();
            $t->enum('status', ['queued', 'notified', 'picked_up', 'expired', 'rejected'])->default('queued');
            $t->timestamp('expires_at')->nullable();
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('last_notified_at')->nullable();
            $t->string('download_token', 64)->nullable(); // sha256(token)
            $t->timestamps();
            $t->unique(['video_id', 'channel_id']);
            $t->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
    }
};