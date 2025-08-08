<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    protected $fillable = ['name', 'creator_name', 'email', 'weight', 'weekly_quota'];

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}