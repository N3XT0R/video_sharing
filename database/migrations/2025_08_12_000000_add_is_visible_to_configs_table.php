<?php

declare(strict_types=1);

use App\Models\Config;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->boolean('is_visible')->default(true);
        });

        $config = Config::query()->where('key', 'dropbox_refresh_token')->firstOrNew();

        if (!$config->exists) {
            $config->setAttribute('key', 'dropbox_refresh_token');
            $config->setAttribute('value', '');
        }
        $config->setAttribute('is_visible', false);
        $config->save();
    }

    public function down(): void
    {
        Schema::table('configs', function (Blueprint $table) {
            $table->dropColumn('is_visible');
        });
    }
};
