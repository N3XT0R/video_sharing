<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Download extends Model
{
    protected $fillable = ['assignment_id', 'downloaded_at', 'ip', 'user_agent', 'bytes_sent'];
    protected $casts = ['downloaded_at' => 'datetime'];

    public function assignment()
    {
        return $this->belongsTo(Assignment::class);
    }
}