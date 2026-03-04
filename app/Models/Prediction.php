<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Prediction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'match_id', 'predicted_winner_id', 'points_earned', 'is_correct',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function match()
    {
        return $this->belongsTo(TennisMatch::class, 'match_id');
    }

    public function predictedWinner()
    {
        return $this->belongsTo(Player::class, 'predicted_winner_id');
    }
}
