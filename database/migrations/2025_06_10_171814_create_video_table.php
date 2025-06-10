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
        Schema::create('video', static function (Blueprint $table) {
            $table->id();
            $table->string('file_name');
            $table->text('description');
            $table->unsignedInteger('starts_at')->nullable()->comment('relevant sequence starts at');
            $table->unsignedInteger('ends_at')->nullable()->comment('relevant sequence end at');
            $table->boolean('was_accepted')->default(false);
            $table->dateTime('accepted_at')->nullable();
            $table->unsignedBigInteger('referenced_id')->nullable();
            $table->timestamps();

            $table->foreign('referenced_id')->references('id')->on('video')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video');
    }
};
