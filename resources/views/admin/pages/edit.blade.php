@extends('layouts.admin')
@section('title', 'Editar: ' . $page->title)

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

<form action="{{ route('admin.pages.update', $page) }}" method="POST" class="space-y-5" x-data="pageEditor()" x-init="init()">
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

            {{-- Toolbar --}}
            <div class="flex flex-wrap gap-1 mb-2 bg-gray-50 p-2 rounded-lg border border-gray-200">
                <button type="button" @click="cmd('bold')" title="Negrita"
                        class="px-3 py-1.5 text-sm font-bold rounded hover:bg-gray-200">B</button>
                <button type="button" @click="cmd('italic')" title="Cursiva"
                        class="px-3 py-1.5 text-sm italic rounded hover:bg-gray-200">I</button>
                <button type="button" @click="cmd('underline')" title="Subrayado"
                        class="px-3 py-1.5 text-sm underline rounded hover:bg-gray-200">U</button>
                <span class="w-px bg-gray-300 mx-1"></span>
                <button type="button" @click="cmd('formatBlock', 'H2')" title="Título"
                        class="px-3 py-1.5 text-sm font-bold rounded hover:bg-gray-200">H2</button>
                <button type="button" @click="cmd('formatBlock', 'H3')" title="Subtítulo"
                        class="px-3 py-1.5 text-sm font-bold rounded hover:bg-gray-200">H3</button>
                <button type="button" @click="cmd('formatBlock', 'P')" title="Párrafo"
                        class="px-3 py-1.5 text-sm rounded hover:bg-gray-200">P</button>
                <span class="w-px bg-gray-300 mx-1"></span>
                <button type="button" @click="cmd('insertUnorderedList')" title="Lista"
                        class="px-3 py-1.5 text-sm rounded hover:bg-gray-200">• Lista</button>
                <button type="button" @click="cmd('insertOrderedList')" title="Lista numerada"
                        class="px-3 py-1.5 text-sm rounded hover:bg-gray-200">1. Lista</button>
                <span class="w-px bg-gray-300 mx-1"></span>
                <button type="button" @click="link()" title="Enlace"
                        class="px-3 py-1.5 text-sm rounded hover:bg-gray-200">🔗 Link</button>
                <button type="button" @click="cmd('removeFormat')" title="Limpiar formato"
                        class="px-3 py-1.5 text-sm rounded hover:bg-gray-200">⨯ Limpiar</button>
            </div>

            {{-- Editor --}}
            <div x-ref="editor" contenteditable="true"
                 @input="$refs.html.value = $refs.editor.innerHTML"
                 class="prose prose-sm max-w-none min-h-[400px] p-4 border border-gray-200 rounded-lg focus:ring-2 focus:ring-tc-primary focus:border-transparent outline-none bg-white"
                 style="white-space: normal;">
                {!! old('content', $page->content) !!}
            </div>
            <textarea x-ref="html" name="content" class="hidden">{{ old('content', $page->content) }}</textarea>
            <p class="text-xs text-gray-400 mt-2">Sugerencia: selecciona el texto y aplica el formato desde la barra.</p>
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

<script>
function pageEditor() {
    return {
        init() {
            // Sync textarea on load
            this.$refs.html.value = this.$refs.editor.innerHTML;
        },
        cmd(command, value = null) {
            this.$refs.editor.focus();
            document.execCommand(command, false, value);
            this.$refs.html.value = this.$refs.editor.innerHTML;
        },
        link() {
            const url = prompt('URL del enlace (ej. https://...)');
            if (url) this.cmd('createLink', url);
        },
    };
}
</script>
@endsection
