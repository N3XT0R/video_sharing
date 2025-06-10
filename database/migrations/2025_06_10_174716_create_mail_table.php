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
        Schema::create('mail', static function (Blueprint $table) {
            $table->uuid()->primary();
            $table->unsignedBigInteger('receiver_id');
            $table->string('subject', 255);
            $table->timestamps();

            $table->foreign('receiver_id')->references('id')->on('receiver')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail');
    }
};
