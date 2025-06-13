<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserMatch extends Model
{
    protected $fillable = [
        // liste des champs de ta table `user_matches`, par exemple :
        'user_id',
        'matched_user_id',
        'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
