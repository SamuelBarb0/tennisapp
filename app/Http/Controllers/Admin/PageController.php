<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use Illuminate\Http\Request;

class PageController extends Controller
{
    /** List the four built-in CMS pages (seeded if missing). */
    public function index()
    {
        // Ensure all four default pages exist
        foreach (array_keys(Page::DEFAULTS) as $slug) {
            Page::findOrSeed($slug);
        }
        $pages = Page::orderByRaw("FIELD(slug, 'reglas','terminos','privacidad','contacto')")
            ->get();
        return view('admin.pages.index', compact('pages'));
    }

    public function edit(Page $page)
    {
        return view('admin.pages.edit', compact('page'));
    }

    public function update(Request $request, Page $page)
    {
        $data = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'content'          => ['required', 'string'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'is_published'     => ['nullable', 'boolean'],
        ]);
        $data['is_published'] = (bool) ($data['is_published'] ?? false);
        $page->update($data);
        return back()->with('success', 'Página actualizada.');
    }
}
