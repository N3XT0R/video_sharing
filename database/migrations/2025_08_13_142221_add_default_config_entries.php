<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    protected function getConfigEntries(): array
    {
        return [
            [
                'key' => 'email_admin_mail',
                'value' => config('mail.log.email', 'info@example.tld'),
                'cast_type' => 'string',
                'is_visible' => 1,
            ],
            [
                'key' => 'download_ttl_hours',
                'value' => '144',
                'cast_type' => 'int',
                'is_visible' => 1,
            ],
            [
                'key' => 'assign_expire_cooldown_days',
                'value' => '14',
                'cast_type' => 'int',
                'is_visible' => 1,
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $entries = $this->getConfigEntries();
        foreach ($entries as $entry) {
            DB::table('configs')
                ->insert($entry);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $entries = $this->getConfigEntries();
        foreach ($entries as $entry) {
            DB::table('configs')
                ->where('key', $entry['key'])
                ->delete();
        }
    }
};
