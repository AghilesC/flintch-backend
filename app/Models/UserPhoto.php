<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UserPhoto extends Model
{
    protected $fillable = [
        'user_id',
        'path',
        'is_main',
    ];

    protected $appends = ['url']; // Ajoute automatiquement à toArray() ou toJson()

    protected $casts = [
        'is_main' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getUrlAttribute(): string
    {
        return url('/api/user/photo/' . $this->path);
    }
}
