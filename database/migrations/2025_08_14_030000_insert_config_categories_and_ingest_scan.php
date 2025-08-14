<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $categories = collect(['default', 'schedule', 'oauth', 'email'])->map(fn($key) => [
            'key' => $key,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();

        DB::table('config_categories')->insert($categories);

        $scheduleId = DB::table('config_categories')->where('key', 'schedule')->value('id');

        $configId = DB::table('configs')->insertGetId([
            'key' => 'ingest:scan',
            'value' => '1',
            'cast_type' => 'bool',
            'is_visible' => 1,
            'config_category_id' => $scheduleId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('config_sub_settings')->insert([
            [
                'config_id' => $configId,
                'key' => 'params',
                'value' => json_encode(['--inbox' => '/srv/ingest/pending/']),
                'cast_type' => 'json',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'config_id' => $configId,
                'key' => 'frequency',
                'value' => '0 * * * *',
                'cast_type' => 'string',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'config_id' => $configId,
                'key' => 'email_on_failure',
                'value' => 'info@example.tld',
                'cast_type' => 'string',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'config_id' => $configId,
                'key' => 'without_overlapping',
                'value' => '1',
                'cast_type' => 'bool',
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    public function down(): void
    {
        $configId = DB::table('configs')->where('key', 'ingest:scan')->value('id');
        DB::table('config_sub_settings')->where('config_id', $configId)->delete();
        DB::table('configs')->where('id', $configId)->delete();
        DB::table('config_categories')->whereIn('key', ['default', 'schedule', 'oauth', 'email'])->delete();
    }
};

