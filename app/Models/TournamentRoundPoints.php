<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentRoundPoints extends Model
{
    protected $fillable = ['tournament_id', 'round', 'points'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }
}
