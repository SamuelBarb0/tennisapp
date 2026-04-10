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
        'start_date', 'end_date', 'is_premium', 'is_active', 'image',
        'api_tournament_key', 'api_event_type_key', 'season', 'status', 'last_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
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

    public function getComputedStatusAttribute(): string
    {
        // Check stored status first
        $stored = $this->attributes['status'] ?? null;
        if ($stored && in_array($stored, ['upcoming', 'in_progress', 'live', 'finished'])) {
            return $stored;
        }

        $now = now()->startOfDay();
        if ($this->start_date && $now->lt($this->start_date)) return 'upcoming';
        if ($this->end_date && $now->gt($this->end_date)) return 'finished';
        return 'in_progress';
    }

    public function getIsFreeAttribute(): bool
    {
        return !$this->is_premium;
    }
}
