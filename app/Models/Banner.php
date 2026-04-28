<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'subtitle', 'image', 'media_type', 'media_url', 'link', 'is_active', 'order',
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
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->orderBy('order');
    }
}
