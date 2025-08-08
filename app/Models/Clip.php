<?php

declare(strict_types=1);

// app/Models/Clip.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Clip extends Model
{
    protected $fillable = ['video_id', 'start_sec', 'end_sec', 'note', 'bundle_key', 'role', 'submitted_by'];

    public function video()
    {
        return $this->belongsTo(Video::class);
    }
}

