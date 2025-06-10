<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Video extends Model
{
    protected $table = 'video';

    protected $fillable = [
        'file_name',
        'description',
        'starts_at',
        'ends_at',
        'was_accepted',
        'accepted_at',
        'referenced_id',
        'updated_at',
        'created_at',
    ];

    protected $casts = [
        'file_name' => 'string',
        'description' => 'string',
        'starts_at' => 'integer',
        'ends_at' => 'integer',
        'referenced_id' => 'integer',
        'accepted_at' => 'datetime',
        'updated_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function setReferencedVideo(self $video): void
    {
        $this->setAttribute('referenced_id', $video->getKey());
    }

    public function referencedVideo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'referenced_id');
    }
}