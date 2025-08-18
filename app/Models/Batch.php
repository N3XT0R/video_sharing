<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Batch extends Model
{
    use HasFactory;

    protected $fillable = ['type', 'started_at', 'finished_at', 'stats'];
    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime', 'stats' => 'array'];
}