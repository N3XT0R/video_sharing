<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->string('creator_name')->nullable();
            $t->string('email')->unique();
            $t->unsignedInteger('weight')->default(1); // für gewichtete RR
            $t->unsignedInteger('weekly_quota')->default(5);
            $t->timestamps();
        });

        DB::table('channels')->insert([
            [
                'creator_name' => 'Julius',
                'name' => 'DashboardHeroes',
                'email' => 'dashboardhero.ger@gmail.com',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => null,
                'name' => 'RLP Dashcam',
                'email' => 'clips@rlpdashcam.com',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => 'Leon',
                'name' => 'Lets Dashcam',
                'email' => 'letsdashcam@web.de',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => 'Julius',
                'name' => 'DashboardHeroes',
                'email' => 'eure-videos@web.de',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => null,
                'name' => 'Road Rave Germany',
                'email' => 'roadravegermany@gmail.com',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => null,
                'name' => 'NEDK - NOCH EIN DASHCAM KANAL',
                'email' => 'nedk.videos@gmail.com',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'creator_name' => 'Videoknecht Tom & Mauzi',
                'name' => 'Dashcam Stories',
                'email' => 'videos@dashcamstories.de',
                'weight' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};