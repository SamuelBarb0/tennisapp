<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * List banners grouped by slot. For single-instance slots (heros) we
     * auto-seed a placeholder row using the slot's defaults so the admin
     * always sees an editable row instead of an empty section + "Crear" CTA.
     */
    public function index()
    {
        foreach (Banner::SLOTS as $slotKey => $cfg) {
            if (($cfg['allows_many'] ?? false)) continue;
            Banner::firstOrCreate(
                ['slot' => $slotKey],
                [
                    'title'      => $cfg['default_title']    ?? '',
                    'subtitle'   => $cfg['default_subtitle'] ?? '',
                    'media_type' => 'image',
                    'is_active'  => true,
                    'show_stats' => true,
                ]
            );
        }

        $bannersBySlot = Banner::orderBy('order')->get()->groupBy('slot');
        return view('admin.banners.index', compact('bannersBySlot'));
    }

    public function create(Request $request)
    {
        // Pre-select the slot when the admin clicks "Crear banner" from a section
        // header (we pass ?slot=home_hero, ?slot=prizes_hero, etc.).
        $slot = $request->query('slot', 'home_carousel');
        if (!array_key_exists($slot, Banner::SLOTS)) $slot = 'home_carousel';
        return view('admin.banners.create', compact('slot'));
    }

    public function store(Request $request)
    {
        $data = $this->validateBanner($request);
        $data['is_active']  = $request->boolean('is_active');
        $data['is_hero']    = $request->boolean('is_hero');
        $data['show_stats'] = $request->boolean('show_stats');

        $this->enforceSingleInstanceSlot($data['slot']);

        // Legacy is_hero behavior is now driven by slot=home_hero, but we keep
        // the column so existing data and queries keep working.
        if ($data['slot'] === 'home_hero') $data['is_hero'] = true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        Banner::create($data);
        return redirect()->route('admin.banners.index')->with('success', 'Banner creado.');
    }

    public function edit(Banner $banner)
    {
        return view('admin.banners.edit', compact('banner'));
    }

    public function update(Request $request, Banner $banner)
    {
        $data = $this->validateBanner($request);
        $data['is_active']  = $request->boolean('is_active');
        $data['is_hero']    = $request->boolean('is_hero');
        $data['show_stats'] = $request->boolean('show_stats');

        $this->enforceSingleInstanceSlot($data['slot'], $banner->id);
        if ($data['slot'] === 'home_hero') $data['is_hero'] = true;

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        $banner->update($data);
        return redirect()->route('admin.banners.index')->with('success', 'Banner actualizado.');
    }

    public function destroy(Banner $banner)
    {
        // Hero slots are fixed page sections — never delete them via this route
        // even if a URL-only request gets through. Index will recreate any
        // accidentally deleted row, but we'd rather just block it here.
        $cfg = Banner::SLOTS[$banner->slot] ?? null;
        if ($cfg && !($cfg['allows_many'] ?? false)) {
            return redirect()->route('admin.banners.index')
                ->with('success', 'Este banner es una sección fija y no se puede eliminar.');
        }

        $banner->delete();
        return redirect()->route('admin.banners.index')->with('success', 'Banner eliminado.');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        return redirect()->route('admin.banners.index')->with('success', 'Estado del banner actualizado.');
    }

    /**
     * Shared validation. `slot` must exist in Banner::SLOTS; everything else
     * mirrors the previous validation contract.
     */
    private function validateBanner(Request $request): array
    {
        return $request->validate([
            'slot'       => 'required|string|in:' . implode(',', array_keys(Banner::SLOTS)),
            'title'      => 'required|string|max:255',
            'subtitle'   => 'nullable|string',
            'link'       => 'nullable|string',
            'is_active'  => 'boolean',
            'is_hero'    => 'boolean',
            'show_stats' => 'boolean',
            'order'      => 'integer',
            'media_type' => 'required|in:image,video',
            'media_url'  => 'nullable|url',
            'image'      => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm|max:20480',
        ]);
    }

    /**
     * Slots like home_hero and prizes_hero allow only one banner. If the admin
     * tries to add a second to the same slot, deactivate the previous one(s)
     * so only the new one renders publicly. Carousel slots ignore this rule.
     */
    private function enforceSingleInstanceSlot(string $slot, ?int $exceptId = null): void
    {
        $cfg = Banner::SLOTS[$slot] ?? null;
        if (!$cfg || ($cfg['allows_many'] ?? false)) return;

        $q = Banner::where('slot', $slot)->where('is_active', true);
        if ($exceptId) $q->where('id', '!=', $exceptId);
        $q->update(['is_active' => false]);
    }
}
