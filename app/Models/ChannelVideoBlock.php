<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChannelVideoBlock extends Model
{
    use HasFactory;
    
    protected $fillable = ['channel_id', 'video_id', 'until'];
    protected $casts = ['until' => 'datetime'];
}