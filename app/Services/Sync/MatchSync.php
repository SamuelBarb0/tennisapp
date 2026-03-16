<?php

namespace App\Services\Sync;

use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\Prediction;
use App\Models\User;
use App\Services\ApiTennisService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MatchSync
{
    // Only sync singles matches
    const SINGLES_TYPES = ['Atp Singles', 'Wta Singles'];

    protected ApiTennisService $api;

    public function __construct(ApiTennisService $api)
    {
        $this->api = $api;
    }

    public function syncFixtures(string $dateStart, string $dateStop, ?int $tournamentKey = null): array
    {
        $fixtures = $this->api->getFixtures($dateStart, $dateStop, $tournamentKey);

        if ($fixtures === null) {
            return ['error' => 'No se pudo conectar con la API'];
        }

        return $this->processMatches($fixtures);
    }

    public function syncLivescores(): array
    {
        $livescores = $this->api->getLivescores();

        if ($livescores === null) {
            return ['error' => 'No se pudo conectar con la API'];
        }

        return $this->processMatches($livescores);
    }

    protected function processMatches(array $matches): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $predictionsScored = 0;

        foreach ($matches as $m) {
            // Only process singles matches
            if (!in_array($m['event_type_type'] ?? '', self::SINGLES_TYPES)) {
                $skipped++;
                continue;
            }

            // Skip cancelled matches
            if (($m['event_status'] ?? '') === 'Cancelled') {
                $skipped++;
                continue;
            }

            // Find or create tournament
            $tournament = Tournament::where('api_tournament_key', $m['tournament_key'])->first();
            if (!$tournament) {
                $type = str_contains($m['event_type_type'], 'Wta') ? 'WTA' : 'ATP';
                $tournament = Tournament::create([
                    'api_tournament_key' => $m['tournament_key'],
                    'api_event_type_key' => $m['event_type_key'] ?? null,
                    'name' => $m['tournament_name'],
                    'slug' => Str::slug($m['tournament_name']),
                    'type' => $type,
                    'season' => $m['tournament_season'] ?? null,
                    'start_date' => $m['event_date'],
                    'end_date' => Carbon::parse($m['event_date'])->addDays(7),
                    'is_active' => true,
                ]);
            }

            // Update tournament dates based on fixtures
            $eventDate = Carbon::parse($m['event_date']);
            if (!$tournament->start_date || $eventDate->lt($tournament->start_date)) {
                $tournament->update(['start_date' => $eventDate]);
            }
            if (!$tournament->end_date || $eventDate->gt($tournament->end_date)) {
                $tournament->update(['end_date' => $eventDate]);
            }

            // Find or create players
            $player1 = $this->findOrCreatePlayer($m['first_player_key'], $m['event_first_player'], $m['event_type_type']);
            $player2 = $this->findOrCreatePlayer($m['second_player_key'], $m['event_second_player'], $m['event_type_type']);

            if (!$player1 || !$player2) {
                $skipped++;
                continue;
            }

            // Determine match status
            $status = $this->resolveStatus($m['event_status'] ?? '', $m['event_live'] ?? '0');

            // Determine winner
            $winnerId = null;
            if ($status === 'finished' && !empty($m['event_winner'])) {
                $winnerId = $m['event_winner'] === 'First Player' ? $player1->id : $player2->id;
            }

            // Build score string from sets
            $score = $this->buildScore($m['scores'] ?? []);

            // Parse scheduled datetime
            $scheduledAt = Carbon::parse($m['event_date'] . ' ' . ($m['event_time'] ?? '00:00'));

            // Parse round
            $round = $this->parseRound($m['tournament_round'] ?? '');

            // Check if match exists
            $existingMatch = TennisMatch::where('api_event_key', $m['event_key'])->first();
            $wasFinished = $existingMatch ? $existingMatch->status === 'finished' : false;

            $matchData = [
                'tournament_id' => $tournament->id,
                'player1_id' => $player1->id,
                'player2_id' => $player2->id,
                'round' => $round,
                'scheduled_at' => $scheduledAt,
                'score' => $score,
                'winner_id' => $winnerId,
                'status' => $status,
            ];

            if ($existingMatch) {
                $existingMatch->update($matchData);
                $updated++;
            } else {
                $matchData['api_event_key'] = $m['event_key'];
                $existingMatch = TennisMatch::create($matchData);
                $created++;
            }

            // Score predictions if match just finished
            if ($status === 'finished' && !$wasFinished && $winnerId) {
                $predictionsScored += $this->scorePredictions($existingMatch);
            }
        }

        Log::info("Match sync completed", compact('created', 'updated', 'skipped', 'predictionsScored'));

        return compact('created', 'updated', 'skipped', 'predictionsScored');
    }

    protected function findOrCreatePlayer($apiKey, string $name, string $eventType): ?Player
    {
        if (empty($apiKey)) return null;

        $player = Player::where('api_player_key', $apiKey)->first();
        if ($player) return $player;

        $category = str_contains($eventType, 'Wta') ? 'WTA' : 'ATP';

        return Player::create([
            'api_player_key' => $apiKey,
            'name' => $name,
            'slug' => Str::slug($name),
            'country' => 'Unknown',
            'nationality_code' => 'UNK',
            'category' => $category,
        ]);
    }

    protected function resolveStatus(string $eventStatus, string $eventLive): string
    {
        if ($eventStatus === 'Finished') return 'finished';
        if ($eventLive === '1') return 'live';
        if (in_array($eventStatus, ['Set 1', 'Set 2', 'Set 3', 'Set 4', 'Set 5'])) return 'live';
        return 'pending';
    }

    protected function buildScore(array $scores): ?string
    {
        if (empty($scores)) return null;

        $parts = [];
        foreach ($scores as $set) {
            $parts[] = ($set['score_first'] ?? '0') . '-' . ($set['score_second'] ?? '0');
        }

        return implode(' ', $parts);
    }

    protected function parseRound(string $round): string
    {
        if (empty($round)) return 'Unknown';

        // Remove tournament name prefix (e.g., "ATP Indian Wells - 1/16-finals" -> "1/16-finals")
        $parts = explode(' - ', $round);
        return end($parts) ?: $round;
    }

    protected function scorePredictions(TennisMatch $match): int
    {
        $predictions = Prediction::where('match_id', $match->id)
            ->whereNull('is_correct')
            ->get();

        $scored = 0;
        $match->loadMissing('tournament');
        $roundPoints = $match->tournament->getPointsForRound($match->round);

        foreach ($predictions as $prediction) {
            $isCorrect = $prediction->predicted_winner_id == $match->winner_id;
            $pointsEarned = $isCorrect ? $roundPoints : 0;

            $prediction->update([
                'is_correct' => $isCorrect,
                'points_earned' => $pointsEarned,
            ]);

            if ($isCorrect) {
                User::where('id', $prediction->user_id)->increment('points', $pointsEarned);
            }

            $scored++;
        }

        return $scored;
    }
}
