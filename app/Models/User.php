<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'avatar', 'phone', 'points', 'is_admin', 'is_blocked',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'is_blocked' => 'boolean',
        ];
    }

    public function predictions()
    {
        return $this->hasMany(Prediction::class);
    }

    public function redemptions()
    {
        return $this->hasMany(PrizeRedemption::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
