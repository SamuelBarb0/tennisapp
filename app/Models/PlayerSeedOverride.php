<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlayerSeedOverride extends Model
{
    protected $fillable = ['tournament_id', 'player_id', 'badge', 'reason'];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player()
    {
        return $this->belongsTo(Player::class);
    }
}
