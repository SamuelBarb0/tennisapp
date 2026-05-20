<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image', 'media_type', 'media_url',
        'link', 'is_active', 'is_hero', 'slot', 'show_stats', 'order',
    ];

    /**
     * Slots define where a banner is rendered in the public site. The admin
     * picks a slot when creating/editing the banner. Adding a slot here makes
     * it appear automatically in the admin form's dropdown.
     */
    public const SLOTS = [
        'home_carousel' => [
            'label'         => 'Carrusel del Home',
            'description'   => 'Slides 1..N del carrusel principal (después del hero).',
            'allows_many'   => true,
            'default_title' => '',
            'default_subtitle' => '',
        ],
        'home_hero' => [
            'label'         => 'Hero del Home',
            'description'   => 'Slide 0 del carrusel — el banner principal "Predice. Compite. Gana.".',
            'allows_many'   => false,
            'default_title'    => 'Predice. Compite. Gana.',
            'default_subtitle' => 'Haz tus pronósticos en los mejores torneos de tenis del mundo y gana premios increíbles.',
        ],
        'prizes_hero' => [
            'label'         => 'Hero de Premios',
            'description'   => 'Cabecera azul de la página /prizes donde los usuarios canjean puntos.',
            'allows_many'   => false,
            'default_title'    => 'Canjea tus puntos',
            'default_subtitle' => 'Gana prediciendo torneos y canjea tus puntos por premios exclusivos',
        ],
    ];

    public function getMediaSrcAttribute(): ?string
    {
        if ($this->media_url) return $this->media_url;
        if ($this->image) return asset('storage/' . $this->image);
        return null;
    }

    protected function casts(): array
    {
        return [
            'is_active'  => 'boolean',
            'is_hero'    => 'boolean',
            'show_stats' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }

    /**
     * Return the active banner for a single-instance slot (home_hero, prizes_hero)
     * with fallbacks applied so callers don't need to handle null. Use this from
     * Blade to render page heros without checking existence.
     */
    public static function forSlot(string $slot): object
    {
        $defaults = self::SLOTS[$slot] ?? [];
        $row = self::where('slot', $slot)->where('is_active', true)->first();

        return (object) [
            'title'      => $row?->title    ?: ($defaults['default_title']    ?? ''),
            'subtitle'   => $row?->subtitle ?: ($defaults['default_subtitle'] ?? ''),
            'image_url'  => $row?->media_src,
            'link'       => $row?->link,
            // Whether the slot's stats block is visible. Defaults to true so
            // un-customized heroes keep their original behavior.
            'show_stats' => $row ? (bool) $row->show_stats : true,
            'exists'     => (bool) $row,
        ];
    }
}
