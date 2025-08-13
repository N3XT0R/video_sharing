<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {

    protected function getConfigEntries(): array
    {
        $mail = config('mail.log.email');
        if (empty($mail)) {
            $mail = 'info@example.tld';
        }

        return [
            [
                'key' => 'email_admin_mail',
                'value' => $mail,
                'cast_type' => 'string',
                'is_visible' => 1,
            ],
            [
                'key' => 'email_your_name',
                'value' => config('mail.log.name', ''),
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
            [
                'key' => 'ingest_inbox_absolute_path',
                'value' => '/srv/ingest/pending/',
                'cast_type' => 'string',
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
        $timestamp = Carbon::now()->format('Y-m-d H:i:s');;
        foreach ($entries as $entry) {
            $entry['created_at'] = $timestamp;
            $entry['updated_at'] = $timestamp;
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
