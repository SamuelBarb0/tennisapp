<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BracketPrediction extends Model
{
    protected $fillable = [
        'tournament_id', 'user_id', 'round', 'position',
        'predicted_winner_id', 'predicted_player_slug', 'predicted_player_name',
        'is_correct', 'points_earned',
        'final_score_prediction',
    ];

    protected function casts(): array
    {
        return ['is_correct' => 'boolean'];
    }

    protected static function booted(): void
    {
        // Keep a stable snapshot of the chosen player so the pick can always be
        // re-linked after player churn (dedupe / re-import / scraping bug), even
        // if the player's row id changes or disappears. Only fill it when we
        // actually have a player — never wipe the snapshot on a SET NULL.
        static::saving(function (BracketPrediction $pred) {
            $needsSnapshot = $pred->predicted_winner_id
                && (empty($pred->predicted_player_slug) || $pred->isDirty('predicted_winner_id'));
            if ($needsSnapshot) {
                $player = Player::find($pred->predicted_winner_id);
                if ($player) {
                    $pred->predicted_player_slug = $player->slug;
                    $pred->predicted_player_name = $player->name;
                }
            }
        });
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
