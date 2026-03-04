<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrizeRedemption extends Model
{
    protected $fillable = [
        'user_id', 'prize_id', 'status', 'admin_notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function prize()
    {
        return $this->belongsTo(Prize::class);
    }
}
