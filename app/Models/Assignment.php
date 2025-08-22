<?php

declare(strict_types=1);

namespace App\Models;

use App\Enum\StatusEnum;
use App\Facades\Cfg;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Notification;

class Assignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'video_id',
        'channel_id',
        'batch_id',
        'status',
        'expires_at',
        'attempts',
        'last_notified_at',
        'download_token',
        'notification_id'
    ];
    protected $casts = ['expires_at' => 'datetime', 'last_notified_at' => 'datetime'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(Batch::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function notification(): BelongsTo
    {
        return $this->belongsTo(Notification::class);
    }

    public function setExpiresAt(?int $ttlDays = null): void
    {
        if (null === $ttlDays) {
            $ttlDays = Cfg::get('expire_after_days', 'default', 6);
        }

        $expiry = $this->expires_at
            ? min($this->expires_at, now()->addDays($ttlDays))
            : now()->addDays($ttlDays);
        $this->setAttribute('expires_at', $expiry);
    }

    public function setNotified(): void
    {
        $this->setAttribute('status', StatusEnum::NOTIFIED->value);
        $this->setAttribute('last_notified_at', now());
    }
}