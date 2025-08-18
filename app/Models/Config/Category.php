<?php

declare(strict_types=1);

namespace App\Models\Config;

use App\Models\Config;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'config_categories';

    protected $fillable = ['slug', 'name', 'is_visible'];

    protected $casts = [
        'is_visible' => 'bool',
    ];

    public function configs(): HasMany
    {
        return $this->hasMany(Config::class, 'config_category_id');
    }
}