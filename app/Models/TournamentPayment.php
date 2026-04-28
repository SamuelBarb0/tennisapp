<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TournamentPayment extends Model
{
    protected $fillable = [
        'user_id', 'tournament_id', 'preference_id', 'mp_payment_id',
        'status', 'amount', 'currency', 'mp_response', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'mp_response' => 'array',
            'paid_at' => 'datetime',
            'amount' => 'decimal:2',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
