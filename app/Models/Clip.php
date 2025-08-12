<?php

declare(strict_types=1);

// app/Models/Clip.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Clip extends Model
{
    use HasFactory;

    protected $fillable = ['video_id', 'start_sec', 'end_sec', 'note', 'bundle_key', 'role', 'submitted_by'];

    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }
}

