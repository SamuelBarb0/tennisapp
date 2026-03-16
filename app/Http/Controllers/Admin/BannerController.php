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
            'order' => 'integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_active'] = $request->boolean('is_active');
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
            'order' => 'integer',
            'image' => 'nullable|image|max:2048',
        ]);
        $data['is_active'] = $request->boolean('is_active');
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
