<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    protected $fillable = ['type', 'started_at', 'finished_at', 'stats'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'stats' => 'array'];
}