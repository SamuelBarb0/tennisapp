@extends('layouts.admin')
@section('title', 'Editar: ' . $page->title)

@push('styles')
{{-- Quill rich text editor — CDN, no API key required --}}
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
    /* Make Quill's editor area match the rest of the admin forms. */
    .ql-toolbar.ql-snow,
    .ql-container.ql-snow { border-color: #e5e7eb; }
    .ql-toolbar.ql-snow  { border-radius: 0.5rem 0.5rem 0 0; background: #f9fafb; padding: 8px; }
    .ql-container.ql-snow { border-radius: 0 0 0.5rem 0.5rem; min-height: 400px; font-size: 0.95rem; }
    .ql-editor { min-height: 400px; }
    .ql-editor img { max-width: 100%; height: auto; border-radius: 8px; margin: 1rem 0; }
    .ql-editor iframe { max-width: 100%; border-radius: 8px; margin: 1rem 0; }
    .ql-editor blockquote { border-left: 4px solid #1e40af; padding-left: 1rem; color: #4b5563; font-style: italic; }
    .ql-editor pre.ql-syntax { background: #1f2937; color: #f9fafb; padding: 1rem; border-radius: 8px; overflow-x: auto; }
    /* Group toolbar rows visually with a thin divider so the dense toolbar reads cleanly. */
    .ql-toolbar.ql-snow .ql-formats { margin-right: 12px; padding-right: 8px; border-right: 1px solid #e5e7eb; }
    .ql-toolbar.ql-snow .ql-formats:last-child { border-right: 0; }
</style>
@endpush

@section('content')
<div class="flex items-center gap-3 mb-6">
    <a href="{{ route('admin.pages.index') }}" class="text-gray-400 hover:text-gray-600">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
    </a>
    <div>
        <h2 class="text-xl font-bold">Editar {{ $page->title }}</h2>
        <p class="text-xs text-gray-500">URL: <code class="bg-gray-100 px-1.5 py-0.5 rounded">/{{ $page->slug }}</code></p>
    </div>
</div>

@if(session('success'))
<div class="mb-4 px-4 py-3 bg-green-50 border border-green-200 text-green-700 rounded-xl text-sm">
    ✓ {{ session('success') }}
</div>
@endif

<form id="page-form" action="{{ route('admin.pages.update', $page) }}" method="POST" class="space-y-5">
    @csrf @method('PATCH')

    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-100">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Título</label>
            <input type="text" name="title" value="{{ old('title', $page->title) }}" required
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none">
        </div>

        <div class="p-5 border-b border-gray-100">
            <label class="block text-sm font-semibold text-gray-700 mb-1.5">Descripción (SEO)</label>
            <input type="text" name="meta_description" value="{{ old('meta_description', $page->meta_description) }}" maxlength="500"
                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-lg focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none"
                   placeholder="Descripción corta para motores de búsqueda (opcional)">
        </div>

        <div class="p-5 border-b border-gray-100">
            <label class="block text-sm font-semibold text-gray-700 mb-3">Contenido</label>

            {{-- Quill mounts itself into #editor; we keep the HTML in a hidden textarea
                 named "content" so the existing PageController::update() validation
                 ('content' => required|string) keeps working unchanged. --}}
            <div id="editor">{!! old('content', $page->content) !!}</div>
            <textarea id="editor-html" name="content" class="hidden">{{ old('content', $page->content) }}</textarea>

            <p class="text-xs text-gray-400 mt-2">
                Usa la barra para dar formato. El botón de imagen (🖼️) sube archivos al servidor automáticamente.
            </p>
        </div>

        <div class="p-5">
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="is_published" value="1" {{ $page->is_published ? 'checked' : '' }}
                       class="w-4 h-4 rounded border-gray-300 text-tc-primary focus:ring-tc-primary">
                <span class="text-sm text-gray-700">Página visible para el público</span>
            </label>
        </div>
    </div>

    <div class="flex items-center gap-3">
        <button type="submit" class="px-6 py-3 bg-tc-primary text-white font-bold rounded-xl hover:bg-tc-primary/90 shadow">
            Guardar cambios
        </button>
        <a href="{{ url('/' . $page->slug) }}" target="_blank" rel="noopener"
           class="px-4 py-3 text-sm text-tc-primary hover:underline">
            Ver página pública →
        </a>
    </div>
</form>

<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    const uploadUrl = @json(route('admin.pages.upload-image'));
    const csrfToken = @json(csrf_token());

    const quill = new Quill('#editor', {
        theme: 'snow',
        placeholder: 'Escribe el contenido de la página…',
        modules: {
            toolbar: {
                container: [
                    // Row 1 — block structure & typography
                    [{ header: [1, 2, 3, 4, false] }],
                    [{ font: [] }, { size: ['small', false, 'large', 'huge'] }],
                    // Row 2 — inline formatting
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ color: [] }, { background: [] }],
                    [{ script: 'sub' }, { script: 'super' }],
                    // Row 3 — blocks & alignment
                    ['blockquote', 'code-block'],
                    [{ list: 'ordered' }, { list: 'bullet' }, { list: 'check' }],
                    [{ indent: '-1' }, { indent: '+1' }],
                    [{ align: [] }, { direction: 'rtl' }],
                    // Row 4 — embeds & utilities
                    ['link', 'image', 'video'],
                    ['clean'],
                ],
                handlers: {
                    // Override the default image button so uploads go through our
                    // /admin/pages/upload-image endpoint instead of base64-embedding.
                    image: function () {
                        const input = document.createElement('input');
                        input.type = 'file';
                        input.accept = 'image/jpeg,image/png,image/gif,image/webp';
                        input.onchange = async () => {
                            const file = input.files && input.files[0];
                            if (!file) return;
                            if (file.size > 4 * 1024 * 1024) {
                                alert('La imagen pesa más de 4MB. Comprímela antes de subirla.');
                                return;
                            }
                            try {
                                const fd = new FormData();
                                fd.append('image', file);
                                const res = await fetch(uploadUrl, {
                                    method: 'POST',
                                    headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
                                    body: fd,
                                });
                                if (!res.ok) {
                                    const text = await res.text();
                                    console.error('Upload failed:', res.status, text);
                                    throw new Error('HTTP ' + res.status);
                                }
                                const { url } = await res.json();
                                const range = quill.getSelection(true);
                                quill.insertEmbed(range.index, 'image', url, 'user');
                                quill.setSelection(range.index + 1, 0, 'silent');
                            } catch (err) {
                                console.error(err);
                                alert('No se pudo subir la imagen: ' + err.message);
                            }
                        };
                        input.click();
                    },
                },
            },
        },
    });

    // Keep the hidden textarea in sync so the form submits the latest HTML.
    const htmlField = document.getElementById('editor-html');
    quill.on('text-change', () => {
        htmlField.value = quill.root.innerHTML;
    });
    // Also sync on submit as a safety net (e.g. browser autofill timing).
    document.getElementById('page-form').addEventListener('submit', () => {
        htmlField.value = quill.root.innerHTML;
    });
})();
</script>
@endsection
