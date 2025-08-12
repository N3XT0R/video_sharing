<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelVideoBlock extends Model
{
    use HasFactory;

    protected $fillable = ['channel_id', 'video_id', 'until'];
    protected $casts = ['until' => 'datetime'];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    // optionales Helper-Scope
    public function scopeActive($query)
    {
        return $query->where('until', '>', now());
    }
}