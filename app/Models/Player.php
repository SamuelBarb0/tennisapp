<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'country', 'nationality_code', 'photo', 'ranking', 'category', 'bio',
        'api_player_key',
    ];

    protected static function booted(): void
    {
        static::creating(function ($player) {
            if (empty($player->slug)) {
                $player->slug = Str::slug($player->name);
            }
        });
    }

    public function matchesAsPlayer1()
    {
        return $this->hasMany(TennisMatch::class, 'player1_id');
    }

    public function matchesAsPlayer2()
    {
        return $this->hasMany(TennisMatch::class, 'player2_id');
    }

    public function wonMatches()
    {
        return $this->hasMany(TennisMatch::class, 'winner_id');
    }

    /**
     * Get ISO 3166-1 alpha-2 country code from IOC nationality code.
     */
    public function getIso2Attribute(): string
    {
        $map = [
            'ALG'=>'dz','AND'=>'ad','ANG'=>'ao','ARG'=>'ar','ARM'=>'am','AUS'=>'au','AUT'=>'at',
            'BAR'=>'bb','BEL'=>'be','BEN'=>'bj','BER'=>'bm','BIH'=>'ba','BOL'=>'bo','BRA'=>'br',
            'BUL'=>'bg','BUR'=>'bf','CAM'=>'kh','CAN'=>'ca','CHI'=>'cl','CHN'=>'cn','COL'=>'co',
            'COS'=>'cr','CRO'=>'hr','CYP'=>'cy','CZE'=>'cz','DEN'=>'dk','DOM'=>'do','ECU'=>'ec',
            'EGY'=>'eg','ESP'=>'es','EST'=>'ee','FIJ'=>'fj','FIN'=>'fi','FRA'=>'fr','GBR'=>'gb',
            'GEO'=>'ge','GER'=>'de','GHA'=>'gh','GRE'=>'gr','GUA'=>'gt','HON'=>'hn','HUN'=>'hu',
            'IND'=>'in','IRA'=>'ir','IRE'=>'ie','ISR'=>'il','ITA'=>'it','IVO'=>'ci','JAM'=>'jm',
            'JOR'=>'jo','JPN'=>'jp','KAZ'=>'kz','KEN'=>'ke','KOR'=>'kr','KOS'=>'xk','KUW'=>'kw',
            'LAT'=>'lv','LEB'=>'lb','LIE'=>'li','LTU'=>'lt','LUX'=>'lu','MAL'=>'my','MDA'=>'md',
            'MEX'=>'mx','MNE'=>'me','MON'=>'mc','MOR'=>'ma','NED'=>'nl','NEP'=>'np','NEW'=>'nz',
            'NIC'=>'ni','NOR'=>'no','NZL'=>'nz','PAK'=>'pk','PAR'=>'py','PER'=>'pe','PHI'=>'ph',
            'POL'=>'pl','POR'=>'pt','PUE'=>'pr','ROU'=>'ro','RSA'=>'za','SAU'=>'sa','SEN'=>'sn',
            'SIN'=>'sg','SLO'=>'si','SRB'=>'rs','SUI'=>'ch','SVK'=>'sk','SWE'=>'se','SYR'=>'sy',
            'THA'=>'th','TPE'=>'tw','TUN'=>'tn','TUR'=>'tr','UKR'=>'ua','URU'=>'uy','USA'=>'us',
            'UZB'=>'uz','VEN'=>'ve','VIE'=>'vn','ZIM'=>'zw','EL '=>'gr','WOR'=>'un',
            // ISO 3166 alpha-3 codes (Sportradar uses these)
            'DEU'=>'de','CHE'=>'ch','NLD'=>'nl','HRV'=>'hr','BGR'=>'bg','RUS'=>'ru',
            'GRC'=>'gr','BLR'=>'by','SVN'=>'si','CHL'=>'cl','KHM'=>'kh','PRY'=>'py',
            'PRT'=>'pt','MAR'=>'ma','MCO'=>'mc','MYS'=>'my','PHL'=>'ph','ARE'=>'ae',
            'SAU'=>'sa','SGP'=>'sg','TWN'=>'tw','VNM'=>'vn','ZAF'=>'za','THA'=>'th',
            'IRN'=>'ir','IRL'=>'ie','ISR'=>'il','CIV'=>'ci','JOR'=>'jo','LBN'=>'lb',
            'LIE'=>'li','SYR'=>'sy','BFA'=>'bf','GHA'=>'gh','SEN'=>'sn','TUN'=>'tn',
            'KWT'=>'kw','NPL'=>'np','NIC'=>'ni','GTM'=>'gt','HND'=>'hn','CRI'=>'cr',
            'BOL'=>'bo','DOM'=>'do','ECU'=>'ec','PER'=>'pe','PRK'=>'kp','BIH'=>'ba',
        ];

        $code = strtoupper(trim($this->nationality_code ?? ''));
        return $map[$code] ?? 'un';
    }

    /**
     * Get flag image URL (16x12 or 32x24 from flagcdn.com).
     */
    public function getFlagUrlAttribute(): string
    {
        return 'https://flagcdn.com/w40/' . $this->iso2 . '.png';
    }
}
