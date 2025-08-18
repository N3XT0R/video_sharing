<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {


    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');
        $defaultId = DB::table('config_categories')->where('slug', 'default')->value('id');
        DB::table('configs')
            ->insert([
                'key' => 'email_get_bcc_notification',
                'value' => 1,
                'cast_type' => 'bool',
                'config_category_id' => $defaultId,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
                'is_visible' => 1,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('configs')->where('key', 'email_get_bcc_notification')->delete();
    }
};
