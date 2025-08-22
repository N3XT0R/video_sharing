<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Video extends Model
{
    use HasFactory;

    protected $fillable = ['hash', 'ext', 'bytes', 'path', 'meta', 'original_name', 'disk', 'preview_url'];
    protected $casts = ['meta' => 'array'];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function clips(): HasMany
    {
        return $this->hasMany(Clip::class);
    }

    public function getDisk(): Filesystem
    {
        return Storage::disk($this->getAttribute('disk'));
    }

    protected static function booted(): void
    {
        static::deleting(function (Video $video) {
            if ($video->exists === false) {
                return true;
            }
            $disk = $video->getDisk();
            return $disk->delete($video->getAttribute('path'));
        });
    }
}