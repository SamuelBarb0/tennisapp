<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BracketPrediction extends Model
{
    protected $fillable = [
        'tournament_id', 'user_id', 'round', 'position',
        'predicted_winner_id', 'is_correct', 'points_earned',
        'final_score_prediction',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function predictedWinner()
    {
        return $this->belongsTo(Player::class, 'predicted_winner_id');
    }
}
