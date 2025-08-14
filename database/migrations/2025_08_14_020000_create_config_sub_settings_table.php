<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('config_sub_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('config_id')->constrained()->cascadeOnDelete();
            $table->string('key', 255);
            $table->text('value')->nullable();
            $table->string('cast_type', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('config_sub_settings');
    }
};
