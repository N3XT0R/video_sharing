<?php

declare(strict_types=1);

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

    public function getPreviewPath(): string
    {
        $videoId = $this->getAttribute('video')->getKey();
        $hash = md5($videoId.'_'.$this->getAttribute('start_sec').'_'.$this->getAttribute('end_sec'));
        return "previews/{$hash}.mp4";
    }
}

