<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'country', 'nationality_code', 'photo', 'ranking', 'category', 'bio',
    ];

    protected static function booted(): void
    {
        static::creating(function ($player) {
            if (empty($player->slug)) {
                $player->slug = Str::slug($player->name);
            }
        });
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(TennisMatch::class, 'player1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(TennisMatch::class, 'player2_id');
    }

    public function wonMatches()
    {
        return $this->hasMany(TennisMatch::class, 'winner_id');
    }
}
