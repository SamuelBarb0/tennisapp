<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentCountdownMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tournament $tournament,
        public int $hoursLeft,
        public Carbon $closesAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⏰ Quedan {$this->hoursLeft}h para predecir {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-countdown',
            with: [
                'user'       => $this->user,
                'tournament' => $this->tournament,
                'hoursLeft'  => $this->hoursLeft,
                'closesAt'   => $this->closesAt,
            ],
        );
    }
}
