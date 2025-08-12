<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'download_token'
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
}