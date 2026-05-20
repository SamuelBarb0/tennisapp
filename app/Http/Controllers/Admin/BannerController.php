<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    public function index()
    {
        $banners = Banner::orderBy('order')->get();
        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return view('admin.banners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'link' => 'nullable|string',
            'is_active' => 'boolean',
            'is_hero' => 'boolean',
            'order' => 'integer',
            'media_type' => 'required|in:image,video',
            'media_url' => 'nullable|url',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm|max:20480',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_hero']   = $request->boolean('is_hero');
        // Only one hero at a time — clear any prior hero before saving this one.
        if ($data['is_hero']) {
            Banner::where('is_hero', true)->update(['is_hero' => false]);
        }
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
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'subtitle' => 'nullable|string',
            'link' => 'nullable|string',
            'is_active' => 'boolean',
            'is_hero' => 'boolean',
            'order' => 'integer',
            'media_type' => 'required|in:image,video',
            'media_url' => 'nullable|url',
            'image' => 'nullable|file|mimes:jpg,jpeg,png,webp,gif,mp4,webm|max:20480',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $data['is_hero']   = $request->boolean('is_hero');
        // Only one hero at a time — clear any prior hero before saving this one.
        if ($data['is_hero']) {
            Banner::where('is_hero', true)->where('id', '!=', $banner->id)->update(['is_hero' => false]);
        }
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('banners', 'public');
        }
        $banner->update($data);
        return redirect()->route('admin.banners.index')->with('success', 'Banner actualizado.');
    }

    public function destroy(Banner $banner)
    {
        $banner->delete();
        return redirect()->route('admin.banners.index')->with('success', 'Banner eliminado.');
    }

    public function toggle(Banner $banner)
    {
        $banner->update(['is_active' => !$banner->is_active]);
        return redirect()->route('admin.banners.index')->with('success', 'Estado del banner actualizado.');
    }
}
