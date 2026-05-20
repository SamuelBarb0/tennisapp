<?php

namespace App\Http\Controllers;

use App\Models\Page;

class PageController extends Controller
{
    public function show(string $slug)
    {
        $page = Page::findOrSeed($slug);
        if (!$page || !$page->is_published) {
            abort(404);
        }
        // Contact page gets a richer template that pulls email / phone / socials
        // from Settings so the customer can update them from /admin/settings and
        // the page reflects the changes (with clickable mailto:/wa.me/ links).
        $view = $slug === 'contacto' ? 'pages.contact' : 'pages.show';
        return view($view, compact('page'));
    }
}
