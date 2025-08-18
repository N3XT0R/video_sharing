<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        DB::table('configs')->where('key', 'download_ttl_hours')->delete();

        $defaultId = DB::table('config_categories')->where('slug', 'default')->value('id');
        DB::table('configs')
            ->insert([
                'key' => 'expire_after_days',
                'value' => '6',
                'cast_type' => 'int',
                'is_visible' => 1,
                'config_category_id' => $defaultId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        DB::table('configs')
            ->insert([
                'key' => 'download_ttl_hours',
                'value' => '144',
                'cast_type' => 'int',
                'is_visible' => 1,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ]);
        DB::table('configs')->where('key', 'expire_after_days')->delete();
    }
};
