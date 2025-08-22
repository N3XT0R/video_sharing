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
            $path = $video->getAttribute('path');
            if (!$path) {
                return true;
            }

            /**
             * @var Clip $clip
             *
             */
            $clip = $video->clips()->first();

            try {
                $storageDisk = $video->getDisk();
                $previewDisk = Storage::disk('public');
                $previewPath = $clip->getPreviewPath();

                if ($storageDisk->exists($path) && !$storageDisk->delete($path)) {
                    \Log::warning('video delete failed', ['video_id' => $video->getKey(), 'path' => $path]);
                    return false;
                }

                if ($previewDisk->exists($previewPath) && !$previewDisk->delete($previewPath)) {
                    \Log::warning('preview delete failed', ['video_id' => $video->getKey(), 'path' => $path]);
                    return false;
                }
            } catch (\Throwable $e) {
                \Log::error('File delete threw',
                    ['video_id' => $video->getKey(), 'path' => $path, 'err' => $e->getMessage(), 'exception' => $e]);
                return false;
            }

            return true;
        });
    }
}