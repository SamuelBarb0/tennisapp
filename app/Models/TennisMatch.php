<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TennisMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'tournament_id', 'player1_id', 'player2_id', 'player1_seed', 'player2_seed',
        'round', 'bracket_position', 'scheduled_at', 'score', 'winner_id',
        'status', 'status_note',
        'api_event_key', 'matchstat_match_id',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'player1_id' => 'integer',
            'player2_id' => 'integer',
            'winner_id' => 'integer',
            'tournament_id' => 'integer',
        ];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function player1()
    {
        return $this->belongsTo(Player::class, 'player1_id');
    }

    public function player2()
    {
        return $this->belongsTo(Player::class, 'player2_id');
    }

    public function winner()
    {
        return $this->belongsTo(Player::class, 'winner_id');
    }

    public function predictions()
    {
        return $this->hasMany(Prediction::class, 'match_id');
    }
}
