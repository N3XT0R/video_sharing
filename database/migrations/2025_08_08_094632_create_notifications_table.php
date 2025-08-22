<?php

declare(strict_types=1);

use App\Enum\NotificationTypeEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $t) {
            $t->id();
            $t->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $t->enum('type', array_map(
                fn(NotificationTypeEnum $e) => $e->value,
                NotificationTypeEnum::cases()
            ));
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
