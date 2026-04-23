<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentTiebreak extends Model
{
    protected $fillable = ['tournament_id', 'user_id', 'manual_rank'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
