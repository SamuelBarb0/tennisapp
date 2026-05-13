<?php

namespace App\Mail;

use App\Models\Tournament;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PredictionConfirmedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $user,
        public Tournament $tournament,
        public ?string $champion = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✓ Tu predicción de {$this->tournament->name} quedó guardada",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.prediction-confirmed',
            with: [
                'user'       => $this->user,
                'tournament' => $this->tournament,
                'champion'   => $this->champion,
            ],
        );
    }
}
