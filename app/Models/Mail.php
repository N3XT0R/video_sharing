<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Mail extends Model
{

    use HasUuids;

    protected $table = 'mail';

    protected $fillable = [
        'receiver_id',
        'subject',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'receiver_id' => 'integer',
        'subject' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function setReceiver(Receiver $receiver): void
    {
        $this->setAttribute('receiver_id', $receiver->getKey());
    }

    public function receiver(): HasOne
    {
        return $this->hasOne(Receiver::class, 'receiver_id');
    }

    public function videoList(): BelongsToMany
    {
        return $this->belongsToMany(Video::class, 'mail_to_video', 'mail_uuid', 'uuid');
    }
}