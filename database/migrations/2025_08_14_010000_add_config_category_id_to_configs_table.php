<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->foreignId('config_category_id')->nullable()->constrained('config_categories');
        });
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('config_category_id');
        });
    }
};
