<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'profile_photo',
        'birthdate',
        'gender',
        'height',
        'weight',
        'sports',
        'fitness_level',
        'goals',
        'availability',
        'location',
        'latitude',
        'longitude',
        'is_premium',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'sports' => 'array',
        'availability' => 'array',
        'is_premium' => 'boolean'
    ];

    // 📸 Relation avec les photos supplémentaires
    public function photos()
    {
        return $this->hasMany(UserPhoto::class);
    }
}
