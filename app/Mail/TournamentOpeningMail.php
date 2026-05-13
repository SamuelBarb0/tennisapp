<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TournamentOpeningMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public Tournament $tournament) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🎾 Ya puedes predecir {$this->tournament->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.tournament-opening',
            with: ['user' => $this->user, 'tournament' => $this->tournament],
        );
    }
}
