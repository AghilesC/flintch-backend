<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserPhoto extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'photo_path',
        'is_main',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
