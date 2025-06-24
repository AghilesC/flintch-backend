<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMatch extends Model
{
    use HasFactory;

    protected $table = 'user_matches';

    protected $fillable = [
        'user_id',
        'matched_user_id',
        'is_mutual',
        'status',
        'matched_at',
    ];

    protected $casts = [
        'is_mutual' => 'boolean',
        'matched_at' => 'datetime',
    ];

    /**
     * Get the user who initiated the match
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who was matched
     */
    public function matchedUser()
    {
        return $this->belongsTo(User::class, 'matched_user_id');
    }

    /**
     * Scope for mutual matches
     */
    public function scopeMutual($query)
    {
        return $query->where('is_mutual', true);
    }

    /**
     * Scope for pending matches (not mutual yet)
     */
    public function scopePending($query)
    {
        return $query->where('is_mutual', false)->where('status', '!=', 'rejected');
    }

    /**
     * Check if two users are matched
     */
    public static function areMatched($userId1, $userId2)
    {
        return self::where(function ($query) use ($userId1, $userId2) {
            $query->where([
                ['user_id', $userId1],
                ['matched_user_id', $userId2],
                ['is_mutual', true],
                ['status', '!=', 'rejected']
            ])->orWhere([
                ['user_id', $userId2],
                ['matched_user_id', $userId1],
                ['is_mutual', true],
                ['status', '!=', 'rejected']
            ]);
        })->exists();
    }
}
