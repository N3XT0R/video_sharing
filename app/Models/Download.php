<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    use HasFactory;

    protected $fillable = ['assignment_id', 'downloaded_at', 'ip', 'user_agent', 'bytes_sent'];
    protected $casts = ['downloaded_at' => 'datetime'];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }
}