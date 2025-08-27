<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $defaultId = DB::table('config_categories')->where('slug', 'default')->value('id');

        DB::table('configs')->insert([
            'key' => 'default_file_system',
            'value' => 'dropbox',
            'cast_type' => 'string',
            'is_visible' => 1,
            'config_category_id' => $defaultId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    public function down(): void
    {
        DB::table('configs')->where('key', 'default_file_system')->delete();
    }
};
