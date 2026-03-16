<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'type', 'location', 'city', 'country', 'surface',
        'start_date', 'end_date', 'is_premium', 'is_active', 'image', 'points_multiplier',
        'api_tournament_key', 'api_event_type_key', 'season',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'points_multiplier' => 'decimal:1',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($tournament) {
            if (empty($tournament->slug)) {
                $tournament->slug = Str::slug($tournament->name);
            }
        });
    }

    public function matches()
    {
        return $this->hasMany(TennisMatch::class);
    }

    public function roundPoints()
    {
        return $this->hasMany(TournamentRoundPoints::class);
    }

    public function getPointsForRound(string $round): int
    {
        $rp = $this->roundPoints()->where('round', $round)->first();
        return $rp ? $rp->points : (int) Setting::get('points_per_correct', 10);
    }

    public function getStatusAttribute(): string
    {
        $now = now()->startOfDay();
        if ($now->lt($this->start_date)) return 'upcoming';
        if ($now->gt($this->end_date)) return 'finished';
        return 'live';
    }
}
