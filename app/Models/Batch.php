<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * @property array<string,mixed>|null $stats
 */

class Batch extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'started_at', 'finished_at', 'stats'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'stats' => 'array'];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function clips(): HasManyThrough
    {
        return $this->hasManyThrough(
            Clip::class,
            Assignment::class,
            'batch_id', // Foreign key on assignments table
            'video_id', // Foreign key on clips table
            'id',       // Local key on batches table
            'video_id'  // Local key on assignments table
        );
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'assignments', 'batch_id', 'channel_id');
    }
}