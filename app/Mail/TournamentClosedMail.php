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
        $emoji = $this->position === 1 ? '🏆' : ($this->position <= 3 ? '🥇' : '🎾');
        return new Envelope(
            subject: "{$emoji} {$this->tournament->name} terminó · #{$this->position}",
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
