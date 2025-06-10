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
        Schema::create('mail_to_video', static function (Blueprint $table) {
            $table->id();
            $table->uuid('mail_uuid')->index();
            $table->unsignedBigInteger('video_id');
            $table->boolean('is_download_confirmed')->default(false);
            $table->boolean('is_usage_confirmed')->default(false);
            $table->timestamps();

            $table->foreign('mail_uuid')->references('uuid')->on('mail')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('video_id')->references('id')->on('video')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_to_video');
    }
};
