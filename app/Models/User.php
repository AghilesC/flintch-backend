<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

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
        'bio',
        'interests',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'sports' => 'array',
        'goals' => 'array',
        'availability' => 'array',
        'is_premium' => 'boolean',
    ];

    public function photos()
    {
        return $this->hasMany(UserPhoto::class);
    }


    /**
     * 🖼️ Accesseur pour obtenir la photo principale
     */
    public function getMainPhotoAttribute()
    {
        $mainPhoto = $this->photos()->where('is_main', true)->first();
        return $mainPhoto ? $mainPhoto->photo_url : null;
    }

    /**
     * ✅ Accesseur profile_photo → convertit chemin en URL
     */
public function getProfilePhotoAttribute($value)
{
    return $value ? Storage::disk('public')->url($value) : null;
}

public function messagesSent()
{
    return $this->hasMany(Message::class, 'sender_id');
}

public function messagesReceived()
{
    return $this->hasMany(Message::class, 'receiver_id');
}


}
