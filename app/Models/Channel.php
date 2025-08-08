<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Channel extends Model
{
    protected $fillable = ['name', 'email', 'weight', 'weekly_quota'];

    public function assignments()
    {
        return $this->hasMany(Assignment::class);
    }
}