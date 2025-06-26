<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'sender_id',
        'receiver_id',
        'message',
        'sent_at',
        'is_read', // ✅ Nouveau champ ajouté
    ];

    protected $dates = ['sent_at'];

    // ✅ Ajout des casts pour is_read
    protected $casts = [
        'is_read' => 'boolean',
        'sent_at' => 'datetime',
    ];

    // Relations (facultatif si besoin plus tard)
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }
}