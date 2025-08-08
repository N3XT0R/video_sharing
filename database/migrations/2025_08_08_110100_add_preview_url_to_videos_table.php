<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('videos', function (Blueprint $t) {
            $t->string('preview_url')->nullable()->after('meta');
        });
    }

    public function down(): void
    {
        Schema::table('videos', function (Blueprint $t) {
            $t->dropColumn('preview_url');
        });
    }
};
