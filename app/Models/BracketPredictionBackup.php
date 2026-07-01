<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BracketPredictionBackup extends Model
{
    protected $fillable = [
        'batch', 'reason', 'tournament_id', 'user_id', 'round', 'position',
        'predicted_winner_id', 'predicted_player_slug', 'predicted_player_name',
        'is_correct', 'points_earned', 'final_score_prediction', 'original_created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
            'original_created_at' => 'datetime',
        ];
    }

    /**
     * Snapshot every bracket prediction for a tournament into the backup table
     * before a destructive operation. Returns [batch, count].
     *
     * @return array{0:string,1:int}
     */
    public static function snapshotTournament(int $tournamentId, string $reason): array
    {
        $batch = $reason . '-' . $tournamentId . '-' . now()->format('YmdHis');
        $rows = BracketPrediction::where('tournament_id', $tournamentId)->get();
        $now = now();
        $payload = $rows->map(fn ($p) => [
            'batch' => $batch,
            'reason' => $reason,
            'tournament_id' => $p->tournament_id,
            'user_id' => $p->user_id,
            'round' => $p->round,
            'position' => $p->position,
            'predicted_winner_id' => $p->predicted_winner_id,
            'predicted_player_slug' => $p->predicted_player_slug,
            'predicted_player_name' => $p->predicted_player_name,
            'is_correct' => $p->is_correct,
            'points_earned' => $p->points_earned,
            'final_score_prediction' => $p->final_score_prediction,
            'original_created_at' => $p->created_at,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($payload, 200) as $chunk) {
            static::insert($chunk);
        }

        return [$batch, count($payload)];
    }
}
