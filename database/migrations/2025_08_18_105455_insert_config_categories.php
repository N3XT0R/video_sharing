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
        $categories = collect(['default', 'oauth', 'email'])->map(fn($key) => [
            'key' => $key,
            'is_visible' => $key !== 'oauth',
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all();
        DB::table('config_categories')->insert($categories);

        $oAuthId = DB::table('config_categories')->where('key', 'oauth')->value('id');
        DB::table('configs')->where('key', 'dropbox_refresh_token')->update([
            'config_category_id' => $oAuthId,
        ]);

        $emailId = DB::table('config_categories')->where('key', 'email')->value('id');
        DB::table('configs')->whereIn('key', ['email_admin_mail', 'email_your_name'])->update([
            'config_category_id' => $emailId,
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('config_categories')->whereIn('key', ['default', 'oauth', 'email'])->delete();
    }
};
