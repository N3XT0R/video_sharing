<?php

// database/migrations/xxxx_xx_xx_xxxxxx_add_original_and_clips.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $t) {
            $t->string('original_name')->nullable()->after('ext');
        });

        Schema::create('clips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('video_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('start_sec')->nullable();
            $t->unsignedInteger('end_sec')->nullable();
            $t->string('note')->nullable();
            $t->string('bundle_key')->nullable();  // gruppiert F/R/Segmente
            $t->string('role', 16)->nullable();    // z.B. F, R, seg1, seg2
            $t->timestamps();
            $t->index(['bundle_key', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clips');
        Schema::table('videos', fn(Blueprint $t) => $t->dropColumn('original_name'));
    }
};
