<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('receiver', static function (Blueprint $table) {
            $table->id();
            $table->string('to', 255);
            $table->string('name', 255);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $stringList = explode(
            ' ',
            'dashboardhero.ger@gmail.com luca@dashcamclipsgermany.com clips@rlpdashcam.com letsdashcam@web.de eure-videos@web.de roadravegermany@gmail.com nedk.videos@gmail.com'
        );

        foreach ($stringList as $email) {
            if (!empty($email)) {
                $now = Carbon::now()->format('Y-m-d H:i:s');
                DB::table('receiver')->insert([
                    'to' => $email,
                    'name' => explode('@', $email)[0],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('receiver');
    }
};
