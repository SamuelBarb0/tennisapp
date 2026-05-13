<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentEmailLog extends Model
{
    protected $table = 'tournament_email_log';
    public $timestamps = true;

    protected $fillable = [
        'tournament_id', 'kind', 'sent_at', 'recipients_count',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public const KIND_OPENING   = 'opening';
    public const KIND_COUNTDOWN = 'countdown';
    public const KIND_CLOSING   = 'closing';
}
