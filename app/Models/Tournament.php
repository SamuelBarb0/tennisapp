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
        'start_date', 'end_date', 'is_premium', 'price', 'is_active', 'featured_on_home', 'image',
        'api_tournament_key', 'api_event_type_key', 'season', 'status', 'last_synced_at',
        'matchstat_season_id', 'matchstat_tournament_id', 'tennisexplorer_slug',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_premium' => 'boolean',
            'is_active' => 'boolean',
            'featured_on_home' => 'boolean',
            'price' => 'decimal:2',
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

    public function payments()
    {
        return $this->hasMany(TournamentPayment::class);
    }

    public function emailLogs()
    {
        return $this->hasMany(TournamentEmailLog::class);
    }

    /**
     * True when this tournament requires payment to predict.
     * Free tournaments (is_premium=false OR price not set) never require payment.
     */
    public function requiresPayment(): bool
    {
        return $this->is_premium && $this->price > 0;
    }

    /**
     * True if the user has an approved payment for this tournament. Free tournaments
     * always return true (no gate).
     */
    public function hasUserPaid(?int $userId): bool
    {
        if (!$this->requiresPayment()) return true;
        if (!$userId) return false;

        return $this->payments()
            ->where('user_id', $userId)
            ->where('status', 'approved')
            ->exists();
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

    /**
     * State the home/cards use to decide which CTA button to render:
     *  - 'live'        → tournament already started; only viewing allowed
     *  - 'open'        → bracket is loaded and predictions are still open
     *  - 'unavailable' → bracket not loaded yet (no first-round matches with real players)
     */
    public function getBracketStateAttribute(): string
    {
        $first = $this->matches()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->first();

        if (!$first) return 'unavailable';
        if (now()->gte($first->scheduled_at)) return 'live';

        // Bracket is "open" when at least one match in the earliest round has two
        // real players (not TBD placeholders).
        $placeholderRe = '/^(Qf|SF|WSF|WQF|F|Ganador|TBD)(\s?\d+)?$/i';
        $earliest = $this->matches()
            ->whereNotIn('status', ['cancelled'])
            ->orderBy('scheduled_at')
            ->with(['player1', 'player2'])
            ->limit(8)
            ->get();

        foreach ($earliest as $m) {
            $p1Real = $m->player1 && !preg_match($placeholderRe, $m->player1->name);
            $p2Real = $m->player2 && !preg_match($placeholderRe, $m->player2->name);
            if ($p1Real && $p2Real) return 'open';
        }
        return 'unavailable';
    }
}
