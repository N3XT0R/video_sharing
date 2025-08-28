<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');

        $categoryId = DB::table('config_categories')->insertGetId([
            'slug' => 'ffmpeg',
            'name' => 'FFMPEG',
            'is_visible' => true,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        DB::table('configs')->insert([
            [
                'key' => 'ffmpeg_bin',
                'value' => '',
                'selectable' => null,
                'cast_type' => 'string',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'ffmpeg_video_codec',
                'value' => 'libx264',
                'selectable' => json_encode(['libx264', 'libx265', 'libvpx-vp9']),
                'cast_type' => 'string',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'ffmpeg_audio_codec',
                'value' => 'aac',
                'selectable' => json_encode(['aac', 'libmp3lame', 'libvorbis']),
                'cast_type' => 'string',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'ffmpeg_preset',
                'value' => 'veryfast',
                'selectable' => json_encode(['ultrafast','superfast','veryfast','faster','fast','medium','slow','slower','veryslow','placebo']),
                'cast_type' => 'string',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'ffmpeg_crf',
                'value' => '28',
                'selectable' => null,
                'cast_type' => 'int',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
            [
                'key' => 'ffmpeg_video_args',
                'value' => json_encode([]),
                'selectable' => null,
                'cast_type' => 'json',
                'is_visible' => 1,
                'config_category_id' => $categoryId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('configs')->whereIn('key', [
            'ffmpeg_bin',
            'ffmpeg_video_codec',
            'ffmpeg_audio_codec',
            'ffmpeg_preset',
            'ffmpeg_crf',
            'ffmpeg_video_args',
        ])->delete();

        DB::table('config_categories')->where('slug', 'ffmpeg')->delete();
    }
};
