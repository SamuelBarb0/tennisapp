<?php

namespace App\Console\Commands;

use App\Mail\PredictionConfirmedMail;
use App\Mail\TournamentClosedMail;
use App\Mail\TournamentCountdownMail;
use App\Mail\TournamentOpeningMail;
use App\Mail\WelcomeMail;
use App\Models\Tournament;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Send one of each automated email to a target address to validate SMTP
 * delivery and HTML rendering in a real mail client.
 *
 *   php artisan tennis:test-emails your@email.com
 *   php artisan tennis:test-emails your@email.com --only=welcome
 *
 * Uses the first available active tournament + the target user as sample data.
 * If the target doesn't have a User row, creates a transient one in memory.
 */
class TennisTestEmails extends Command
{
    protected $signature = 'tennis:test-emails
                            {email : Where to send the test emails}
                            {--only= : Only send one kind (welcome|opening|countdown|prediction|closing)}';

    protected $description = 'Send each automated email to a target address for SMTP + design validation';

    public function handle(): int
    {
        $email = $this->argument('email');
        $only  = $this->option('only');

        // Sample data: pick a real tournament so links and dates look natural.
        // Falls back to a transient stub so the command works even on a fresh DB.
        $tournament = Tournament::whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderByDesc('start_date')
            ->first()
            ?? $this->makeStubTournament();

        // Use an existing user if there's one with this email, otherwise stub.
        $user = User::where('email', $email)->first() ?? $this->makeStubUser($email);

        $this->info("Sending test emails to {$email} using tournament \"{$tournament->name}\"…");
        $this->newLine();

        $kinds = [
            'welcome'    => fn() => new WelcomeMail($user),
            'opening'    => fn() => new TournamentOpeningMail($user, $tournament),
            'countdown'  => fn() => new TournamentCountdownMail($user, $tournament, 12, now()->addHours(12)),
            'prediction' => fn() => new PredictionConfirmedMail($user, $tournament, 'Jannik Sinner'),
            'closing'    => fn() => new TournamentClosedMail(
                user: $user,
                tournament: $tournament,
                position: 3,
                totalParticipants: 47,
                points: 285,
                correctPicks: 19,
                totalPicks: 32,
                prize: '🥉 3er lugar',
            ),
        ];

        if ($only && !isset($kinds[$only])) {
            $this->error("Unknown kind '{$only}'. Use one of: " . implode(', ', array_keys($kinds)));
            return self::FAILURE;
        }
        if ($only) {
            $kinds = [$only => $kinds[$only]];
        }

        $ok = 0;
        $fail = 0;
        foreach ($kinds as $kind => $factory) {
            try {
                Mail::to($email)->send($factory());
                $this->line("  <fg=green>✓</> {$kind}");
                $ok++;
            } catch (\Throwable $e) {
                $this->line("  <fg=red>✗</> {$kind}: " . $e->getMessage());
                $fail++;
            }
        }

        $this->newLine();
        $this->info("✓ Sent {$ok}, failed {$fail}.");
        if ($fail > 0) {
            $this->warn('Check storage/logs/laravel.log for SMTP details.');
        }
        return $fail === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function makeStubTournament(): Tournament
    {
        $t = new Tournament();
        $t->id          = 999999;
        $t->name        = 'Roland Garros';
        $t->slug        = 'roland-garros-test';
        $t->type        = 'ATP Grand Slam';
        $t->surface     = 'Clay';
        $t->city        = 'Paris';
        $t->country     = 'France';
        $t->start_date  = now()->addDays(5);
        $t->end_date    = now()->addDays(19);
        $t->is_premium  = false;
        return $t;
    }

    private function makeStubUser(string $email): User
    {
        $u = new User();
        $u->id    = 999999;
        $u->name  = 'Carlos';
        $u->email = $email;
        return $u;
    }
}
