<?php

namespace App\Services\Sync;

use App\Models\BracketPrediction;
use App\Models\Player;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\Prediction;
use App\Models\User;
use App\Services\SportradarService;
use App\Services\Sportradar\TournamentRegistry;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MatchSync
{
    protected SportradarService $api;

    public function __construct(SportradarService $api)
    {
        $this->api = $api;
    }

    /**
     * Sync all matches for a specific tournament (by season).
     */
    public function syncTournament(Tournament $tournament): array
    {
        $seasonId = $tournament->api_event_type_key;

        if (!$seasonId) {
            return ['error' => "Tournament {$tournament->name} has no season ID"];
        }

        $summaries = $this->api->getSeasonSummaries($seasonId);

        if ($summaries === null) {
            return ['error' => 'No se pudo conectar con Sportradar'];
        }

        return $this->processSummaries($summaries, $tournament);
    }

    /**
     * Sync all active tournaments' matches.
     */
    public function syncAll(): array
    {
        $tournaments = Tournament::whereNotNull('api_event_type_key')
            ->where('is_active', true)
            ->get();

        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'predictionsScored' => 0];

        foreach ($tournaments as $tournament) {
            $result = $this->syncTournament($tournament);

            if (isset($result['error'])) {
                Log::warning("Skipping {$tournament->name}: {$result['error']}");
                continue;
            }

            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];
            $totals['predictionsScored'] += $result['predictionsScored'];
        }

        return $totals;
    }

    /**
     * Sync live matches only.
     */
    public function syncLive(): array
    {
        $summaries = $this->api->getLiveSummaries();

        if ($summaries === null) {
            return ['error' => 'No se pudo obtener live scores'];
        }

        // Filter only our target competitions
        $filtered = array_filter($summaries, function ($s) {
            $compId = $s['sport_event']['sport_event_context']['competition']['id'] ?? null;
            return $compId && TournamentRegistry::isTarget($compId);
        });

        if (empty($filtered)) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0, 'predictionsScored' => 0];
        }

        // Group by competition and process
        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'predictionsScored' => 0];

        foreach ($filtered as $summary) {
            $compId = $summary['sport_event']['sport_event_context']['competition']['id'];
            $tournament = Tournament::where('api_tournament_key', $compId)->first();

            if (!$tournament) continue;

            $result = $this->processSummaries([$summary], $tournament);
            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['predictionsScored'] += $result['predictionsScored'];
        }

        return $totals;
    }

    protected function processSummaries(array $summaries, Tournament $tournament): array
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $predictionsScored = 0;

        foreach ($summaries as $s) {
            $sportEvent = $s['sport_event'] ?? null;
            $eventStatus = $s['sport_event_status'] ?? null;

            if (!$sportEvent || !$eventStatus) {
                $skipped++;
                continue;
            }

            $eventId = $sportEvent['id'];
            $competitors = $sportEvent['competitors'] ?? [];

            if (count($competitors) < 2) {
                $skipped++;
                continue;
            }

            // Skip matches with placeholder/TBD competitors
            $hasPlaceholder = false;
            foreach ($competitors as $c) {
                $name = $c['name'] ?? '';
                if (!str_contains($name, ',') && !str_contains($name, ' ') && strlen($name) < 10) {
                    $hasPlaceholder = true;
                    break;
                }
            }
            if ($hasPlaceholder) {
                $skipped++;
                continue;
            }

            // Skip qualification rounds — only main draw
            $roundName = $sportEvent['sport_event_context']['round']['name'] ?? '';
            $phase = $sportEvent['sport_event_context']['stage']['phase'] ?? '';
            if ($phase === 'qualification') {
                $skipped++;
                continue;
            }

            $round = TournamentRegistry::mapRound($roundName);

            // Find or create players
            $player1 = $this->findOrCreatePlayer($competitors[0], $tournament);
            $player2 = $this->findOrCreatePlayer($competitors[1], $tournament);

            if (!$player1 || !$player2) {
                $skipped++;
                continue;
            }

            // Determine status
            $status = $this->resolveStatus($eventStatus['status'] ?? '', $eventStatus['match_status'] ?? '');

            // Determine winner
            $winnerId = null;
            if ($status === 'finished' && !empty($eventStatus['winner_id'])) {
                $winnerSrId = $eventStatus['winner_id'];
                if ($winnerSrId === $competitors[0]['id']) {
                    $winnerId = $player1->id;
                } elseif ($winnerSrId === $competitors[1]['id']) {
                    $winnerId = $player2->id;
                }
            }

            // Build score from period_scores
            $score = $this->buildScore($eventStatus['period_scores'] ?? []);

            // Scheduled time
            $scheduledAt = Carbon::parse($sportEvent['start_time']);

            // Check existing match
            $existingMatch = TennisMatch::where('api_event_key', $eventId)->first();
            $wasFinished = $existingMatch ? $existingMatch->status === 'finished' : false;

            // Bracket position from first competitor's bracket_number
            $bracketPos = $competitors[0]['bracket_number'] ?? null;

            $matchData = [
                'tournament_id' => $tournament->id,
                'player1_id' => $player1->id,
                'player2_id' => $player2->id,
                'round' => $round,
                'bracket_position' => $bracketPos,
                'scheduled_at' => $scheduledAt,
                'score' => $score,
                'winner_id' => $winnerId,
                'status' => $status,
            ];

            if ($existingMatch) {
                $existingMatch->update($matchData);
                $updated++;
            } else {
                $matchData['api_event_key'] = $eventId;
                $existingMatch = TennisMatch::create($matchData);
                $created++;
            }

            // Score predictions if match just finished
            if ($status === 'finished' && !$wasFinished && $winnerId) {
                $predictionsScored += $this->scorePredictions($existingMatch);
            }
        }

        // Update tournament status
        $this->updateTournamentStatus($tournament);

        Log::info("Match sync for {$tournament->name}", compact('created', 'updated', 'skipped', 'predictionsScored'));
        return compact('created', 'updated', 'skipped', 'predictionsScored');
    }

    protected function findOrCreatePlayer(array $competitor, Tournament $tournament): ?Player
    {
        $srId = $competitor['id'] ?? null;
        if (!$srId) return null;

        $player = Player::where('api_player_key', $srId)->first();
        if ($player) return $player;

        $name = $this->formatPlayerName($competitor['name'] ?? 'Unknown');
        $countryCode = $competitor['country_code'] ?? 'UNK';
        $country = $competitor['country'] ?? 'Unknown';

        return Player::create([
            'api_player_key' => $srId,
            'name' => $name,
            'slug' => Str::slug($name),
            'country' => $country,
            'nationality_code' => $countryCode,
            'ranking' => $competitor['seed'] ?? null,
            'category' => str_contains($tournament->type, 'WTA') || str_contains($tournament->name, 'WTA') ? 'WTA' : 'ATP',
        ]);
    }

    protected function formatPlayerName(string $name): string
    {
        if (str_contains($name, ',')) {
            $parts = explode(',', $name, 2);
            return trim($parts[1]) . ' ' . trim($parts[0]);
        }
        return $name;
    }

    protected function resolveStatus(string $status, string $matchStatus): string
    {
        return match ($status) {
            'closed', 'ended' => 'finished',
            'live' => 'live',
            'cancelled', 'postponed' => 'cancelled',
            default => 'pending',
        };
    }

    protected function buildScore(array $periodScores): ?string
    {
        if (empty($periodScores)) return null;

        // Sort by set number
        usort($periodScores, fn($a, $b) => ($a['number'] ?? 0) <=> ($b['number'] ?? 0));

        $parts = [];
        foreach ($periodScores as $set) {
            if (($set['type'] ?? '') !== 'set') continue;
            $parts[] = ($set['home_score'] ?? 0) . '-' . ($set['away_score'] ?? 0);
        }

        return $parts ? implode(' ', $parts) : null;
    }

    protected function updateTournamentStatus(Tournament $tournament): void
    {
        $totalMatches = $tournament->matches()->count();
        $finishedMatches = $tournament->matches()->where('status', 'finished')->count();
        $liveMatches = $tournament->matches()->where('status', 'live')->count();

        if ($totalMatches > 0 && $finishedMatches === $totalMatches) {
            $tournament->update(['status' => 'finished']);
        } elseif ($liveMatches > 0) {
            $tournament->update(['status' => 'live']);
        } elseif ($finishedMatches > 0) {
            $tournament->update(['status' => 'in_progress']);
        } else {
            $tournament->update(['status' => 'upcoming']);
        }
    }

    protected function scorePredictions(TennisMatch $match): int
    {
        $scored = 0;
        $match->loadMissing('tournament');
        $roundPoints = $match->tournament->getPointsForRound($match->round);

        // Score old-style predictions (per-match)
        $predictions = Prediction::where('match_id', $match->id)
            ->whereNull('is_correct')
            ->get();

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

        // Score bracket predictions
        // Calculate position: the match's index within its round (1-based)
        $roundMatches = TennisMatch::where('tournament_id', $match->tournament_id)
            ->where('round', $match->round)
            ->orderByRaw('COALESCE(bracket_position, 999999)')
            ->orderBy('scheduled_at')
            ->pluck('id')
            ->values();
        $position = $roundMatches->search($match->id) + 1;

        if ($position > 0) {
            $bracketPreds = BracketPrediction::where('tournament_id', $match->tournament_id)
                ->where('round', $match->round)
                ->where('position', $position)
                ->whereNull('is_correct')
                ->get();

            foreach ($bracketPreds as $bp) {
                $isCorrect = $bp->predicted_winner_id == $match->winner_id;
                $pointsEarned = $isCorrect ? $roundPoints : 0;

                $bp->update([
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                ]);

                if ($isCorrect) {
                    User::where('id', $bp->user_id)->increment('points', $pointsEarned);
                }

                $scored++;
            }
        }

        return $scored;
    }
}
