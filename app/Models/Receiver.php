<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Receiver extends Model
{
    protected $table = 'receiver';

    protected $fillable = [
        'to',
        'name',
        'is_active',
    ];

    protected $casts = [
        'to' => 'string',
        'name' => 'string',
        'is_active' => 'bool',
    ];
}