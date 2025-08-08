<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Assignment extends Model
{
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

    public function video()
    {
        return $this->belongsTo(Video::class);
    }

    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class);
    }
}