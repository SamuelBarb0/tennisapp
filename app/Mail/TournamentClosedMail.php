<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentClosedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tournament $tournament,
        public int $position,
        public int $totalParticipants,
        public int $points,
        public int $correctPicks,
        public int $totalPicks,
        public ?string $prize = null,
    ) {}

    public function envelope(): Envelope
    {
        // Subject deliberately plain (no emoji, no "#", no exclamation):
        // Hotmail/Outlook spam filters penalize emoji + numbers + punctuation
        // in subjects as a marketing-style pattern. Keep it descriptive but
        // simple.
        return new Envelope(
            subject: "Resumen de {$this->tournament->name} - Posicion {$this->position}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-closed',
            with: [
                'user'              => $this->user,
                'tournament'        => $this->tournament,
                'position'          => $this->position,
                'totalParticipants' => $this->totalParticipants,
                'points'            => $this->points,
                'correctPicks'      => $this->correctPicks,
                'totalPicks'        => $this->totalPicks,
                'prize'             => $this->prize,
            ],
        );
    }
}
