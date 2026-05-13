<?php

namespace App\Console\Commands;

use App\Mail\TournamentClosedMail;
use App\Mail\TournamentCountdownMail;
use App\Mail\TournamentOpeningMail;
use App\Models\BracketPrediction;
use App\Models\TennisMatch;
use App\Models\Tournament;
use App\Models\TournamentEmailLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the three mass tournament emails when their conditions are met.
 * Idempotent — uses tournament_email_log to ensure each (tournament, kind)
 * blast goes out at most once.
 *
 * 1) Opening   → bracket has at least one match with two real (non-TBD) players
 * 2) Countdown → first match starts in <= 24 hours AND opening already sent
 * 3) Closing   → final match finished in last 24h AND scoring done
 *
 * Run from the scheduler every hour. Safe to run more frequently — guard rails
 * prevent duplicates.
 */
class TennisSendTournamentEmails extends Command
{
    protected $signature = 'tennis:send-tournament-emails {--dry-run : Preview without sending}';
    protected $description = 'Dispatch tournament opening / countdown / closing emails based on state';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info($dry ? '[DRY-RUN] Checking tournaments…' : 'Checking tournaments…');

        $opening   = $this->processOpening($dry);
        $countdown = $this->processCountdown($dry);
        $closing   = $this->processClosing($dry);

        $this->table(
            ['Stage', 'Tournaments triggered', 'Emails sent'],
            [
                ['Opening',   $opening['tournaments'],   $opening['emails']],
                ['Countdown', $countdown['tournaments'], $countdown['emails']],
                ['Closing',   $closing['tournaments'],   $closing['emails']],
            ]
        );

        return self::SUCCESS;
    }

    /** Tournaments whose bracket just became "playable". */
    private function processOpening(bool $dry): array
    {
        $stats = ['tournaments' => 0, 'emails' => 0];

        $tournaments = Tournament::where('is_active', true)
            ->whereDoesntHave('emailLogs', fn($q) => $q->where('kind', TournamentEmailLog::KIND_OPENING))
            ->whereIn('status', ['upcoming', 'in_progress'])
            ->get();

        foreach ($tournaments as $t) {
            if (!$this->bracketIsReady($t)) continue;

            $sent = $this->blast($t, TournamentEmailLog::KIND_OPENING, $dry, function ($user) use ($t) {
                return new TournamentOpeningMail($user, $t);
            }, $this->allActiveUsers());

            $stats['tournaments']++;
            $stats['emails'] += $sent;
        }
        return $stats;
    }

    /** Tournaments closing within 24h: warn users who haven't predicted yet. */
    private function processCountdown(bool $dry): array
    {
        $stats = ['tournaments' => 0, 'emails' => 0];

        $tournaments = Tournament::where('is_active', true)
            ->whereDoesntHave('emailLogs', fn($q) => $q->where('kind', TournamentEmailLog::KIND_COUNTDOWN))
            ->whereHas('emailLogs', fn($q) => $q->where('kind', TournamentEmailLog::KIND_OPENING))
            ->whereIn('status', ['upcoming', 'in_progress'])
            ->get();

        foreach ($tournaments as $t) {
            $firstMatch = $t->matches()
                ->whereNotIn('status', ['cancelled'])
                ->orderBy('scheduled_at')
                ->first();
            if (!$firstMatch || !$firstMatch->scheduled_at) continue;

            $hoursLeft = now()->diffInHours($firstMatch->scheduled_at, false);
            if ($hoursLeft <= 0 || $hoursLeft > 24) continue;

            // Recipients: users who haven't saved any prediction for this tournament.
            $predictedUserIds = BracketPrediction::where('tournament_id', $t->id)
                ->distinct()->pluck('user_id');
            $recipients = User::where('is_blocked', false)
                ->whereNotNull('email')
                ->whereNotIn('id', $predictedUserIds)
                ->get();

            $sent = $this->blast(
                $t,
                TournamentEmailLog::KIND_COUNTDOWN,
                $dry,
                fn($user) => new TournamentCountdownMail($user, $t, max(1, (int) $hoursLeft), $firstMatch->scheduled_at),
                $recipients,
            );

            $stats['tournaments']++;
            $stats['emails'] += $sent;
        }
        return $stats;
    }

    /** Tournaments where the final has finished — send a personalized closing. */
    private function processClosing(bool $dry): array
    {
        $stats = ['tournaments' => 0, 'emails' => 0];

        $tournaments = Tournament::where('is_active', true)
            ->whereDoesntHave('emailLogs', fn($q) => $q->where('kind', TournamentEmailLog::KIND_CLOSING))
            ->get();

        foreach ($tournaments as $t) {
            // Final = round F. Must exist and be finished.
            $final = $t->matches()->where('round', 'F')->orderByDesc('bracket_position')->first();
            if (!$final || $final->status !== 'finished') continue;

            // Build leaderboard for this tournament (user => points)
            $leaderboard = BracketPrediction::where('tournament_id', $t->id)
                ->whereNotNull('points_earned')
                ->selectRaw('user_id, SUM(points_earned) as pts, SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct, COUNT(*) as total')
                ->groupBy('user_id')
                ->orderByDesc('pts')
                ->get();

            if ($leaderboard->isEmpty()) {
                // Nobody participated; mark as done so we don't keep checking.
                if (!$dry) {
                    TournamentEmailLog::create([
                        'tournament_id'    => $t->id,
                        'kind'             => TournamentEmailLog::KIND_CLOSING,
                        'sent_at'          => now(),
                        'recipients_count' => 0,
                    ]);
                }
                continue;
            }

            $totalParticipants = $leaderboard->count();
            $sentCount = 0;
            $position = 0;
            foreach ($leaderboard as $row) {
                $position++;
                $user = User::find($row->user_id);
                if (!$user || !$user->email) continue;

                $prize = $this->prizeForPosition($position);

                if ($dry) {
                    $this->line("  [DRY] {$user->email} · {$t->name} · #{$position} · {$row->pts}pts");
                    $sentCount++;
                    continue;
                }

                try {
                    Mail::to($user->email)->send(new TournamentClosedMail(
                        user: $user,
                        tournament: $t,
                        position: $position,
                        totalParticipants: $totalParticipants,
                        points: (int) $row->pts,
                        correctPicks: (int) $row->correct,
                        totalPicks: (int) $row->total,
                        prize: $prize,
                    ));
                    $sentCount++;
                } catch (\Throwable $e) {
                    Log::warning('Closing mail failed', [
                        'user'       => $user->id,
                        'tournament' => $t->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            if (!$dry) {
                TournamentEmailLog::create([
                    'tournament_id'    => $t->id,
                    'kind'             => TournamentEmailLog::KIND_CLOSING,
                    'sent_at'          => now(),
                    'recipients_count' => $sentCount,
                ]);
            }

            $stats['tournaments']++;
            $stats['emails'] += $sentCount;
        }
        return $stats;
    }

    /** True when bracket has at least one R128/R64/R32 match with two real players. */
    private function bracketIsReady(Tournament $t): bool
    {
        $placeholder = '/^(Qf|SF|WSF|WQF|F|Ganador|TBD)(\s?\d+)?$/i';
        $earliest = $t->matches()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->with(['player1', 'player2'])
            ->limit(8)
            ->get();

        foreach ($earliest as $m) {
            $p1 = $m->player1?->name ?? '';
            $p2 = $m->player2?->name ?? '';
            if ($p1 && $p2 && !preg_match($placeholder, $p1) && !preg_match($placeholder, $p2)) {
                return true;
            }
        }
        return false;
    }

    /** Generic blast: send the same Mailable factory to each recipient and log. */
    private function blast(Tournament $t, string $kind, bool $dry, callable $mailFactory, $recipients): int
    {
        $count = 0;
        foreach ($recipients as $user) {
            if (!$user->email) continue;
            if ($dry) {
                $this->line("  [DRY] {$user->email} · {$t->name} · {$kind}");
                $count++;
                continue;
            }
            try {
                Mail::to($user->email)->send($mailFactory($user));
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Tournament blast failed', [
                    'user'       => $user->id,
                    'tournament' => $t->id,
                    'kind'       => $kind,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        if (!$dry) {
            TournamentEmailLog::create([
                'tournament_id'    => $t->id,
                'kind'             => $kind,
                'sent_at'          => now(),
                'recipients_count' => $count,
            ]);
        }
        return $count;
    }

    /** All active, non-blocked users with email. */
    private function allActiveUsers()
    {
        return User::where('is_blocked', false)->whereNotNull('email')->get();
    }

    /**
     * Stub prize lookup. Customer will define real prize tiers later — for now
     * we just label the podium so the closing email looks meaningful.
     */
    private function prizeForPosition(int $position): ?string
    {
        return match (true) {
            $position === 1   => '🏆 1er lugar',
            $position === 2   => '🥈 2do lugar',
            $position === 3   => '🥉 3er lugar',
            default           => null,
        };
    }
}
