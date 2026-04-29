@extends('layouts.admin')
@section('title', 'Sincronización API')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">

    {{-- Header --}}
    <div>
        <h2 class="text-2xl font-bold text-gray-900">Sincronización API Tennis</h2>
        <p class="text-sm text-gray-500 mt-1">Conexión con <strong>Matchstat (Tennis API ATP/WTA/ITF)</strong>. La sincronización corre automáticamente cada 2 minutos durante horas de juego.</p>
    </div>

    {{-- Auto-sync status banner — color depends on heartbeat freshness --}}
    @php
        $cronOk = $cronStatus['ok'] ?? false;
        $heartbeat = $cronStatus['last'] ?? null;
        $heartbeatHuman = $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat)->diffForHumans() : 'nunca';
    @endphp
    @if($cronOk)
    <div class="bg-gradient-to-br from-green-50 to-emerald-50 border border-green-200 rounded-2xl p-5 flex items-start gap-4">
        <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center text-white shrink-0 relative">
            <span class="absolute inset-0 rounded-xl bg-green-400 animate-ping opacity-50"></span>
            <svg class="w-6 h-6 relative" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
        </div>
        <div class="flex-1">
            <h3 class="font-bold text-green-900">Cron funcionando ✓</h3>
            <p class="text-sm text-green-700 mt-1">
                El scheduler corrió hace <strong>{{ $heartbeatHuman }}</strong>.
                Los partidos se sincronizan cada 2 min entre 10:00–23:59 UTC y los rankings cada lunes a las 5:00 UTC.
            </p>
        </div>
    </div>
    @else
    <div class="bg-gradient-to-br from-red-50 to-rose-50 border-2 border-red-300 rounded-2xl p-5 flex items-start gap-4">
        <div class="w-12 h-12 bg-red-500 rounded-xl flex items-center justify-center text-white shrink-0">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
        </div>
        <div class="flex-1">
            <h3 class="font-bold text-red-900">Cron NO está corriendo</h3>
            <p class="text-sm text-red-700 mt-1">
                Último heartbeat: <strong>{{ $heartbeatHuman }}</strong>.
                Si nunca se ha registrado o tiene más de 2 minutos, configura el cron en tu servidor:
            </p>
            <pre class="mt-3 bg-red-900 text-green-300 p-3 rounded-lg text-xs overflow-x-auto">* * * * * cd {{ base_path() }} && php artisan schedule:run >> /dev/null 2>&1</pre>
            <p class="text-xs text-red-600 mt-2">
                En Hostinger: Panel → <strong>Cron Jobs</strong> → Crear nuevo → Frecuencia "cada minuto" → Comando arriba.
            </p>
        </div>
    </div>
    @endif

    {{-- Manual sync cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Rankings --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-100 rounded-xl flex items-center justify-center">
                        <svg class="w-5 h-5 text-yellow-600" fill="currentColor" viewBox="0 0 20 20"><path d="M10 15l-5.878 3.09 1.123-6.545L.489 6.91l6.572-.955L10 0l2.939 5.955 6.572.955-4.756 4.635 1.123 6.545z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-bold">Rankings ATP/WTA</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Top 200 de cada tour</p>
                    </div>
                </div>
            </div>
            <div class="text-xs text-gray-500 mb-3">
                Última: <strong class="text-gray-800">{{ $lastSync['rankings'] ?? 'Nunca' }}</strong>
            </div>
            <form method="POST" action="{{ route('admin.api-sync.rankings') }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover transition">Sincronizar ahora</button>
            </form>
        </div>

        {{-- Live --}}
        <div class="bg-white rounded-2xl border border-gray-200 p-6">
            <div class="flex items-start justify-between mb-4">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-red-100 rounded-xl flex items-center justify-center">
                        <span class="relative flex h-3 w-3">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-3 w-3 bg-red-500"></span>
                        </span>
                    </div>
                    <div>
                        <h3 class="font-bold">Live + Resultados</h3>
                        <p class="text-xs text-gray-400 mt-0.5">Fixtures + scores de torneos activos</p>
                    </div>
                </div>
            </div>
            <div class="text-xs text-gray-500 mb-3">
                Última: <strong class="text-gray-800">{{ $lastSync['live'] ?? 'Nunca' }}</strong>
            </div>
            <form method="POST" action="{{ route('admin.api-sync.live') }}">
                @csrf
                <button type="submit" class="w-full px-4 py-2.5 bg-tc-primary text-white rounded-xl text-sm font-bold hover:bg-tc-primary-hover transition">Sincronizar ahora</button>
            </form>
        </div>
    </div>

    {{-- Linked tournaments --}}
    <div class="bg-white rounded-2xl border border-gray-200 overflow-hidden">
        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
            <div>
                <h3 class="font-bold">Torneos enlazados a Matchstat</h3>
                <p class="text-xs text-gray-400 mt-0.5">Solo estos se sincronizan automáticamente</p>
            </div>
            @if($unlinkedCount > 0)
            <span class="text-xs text-amber-600 bg-amber-50 border border-amber-200 px-3 py-1 rounded-full font-bold">
                {{ $unlinkedCount }} torneo(s) sin enlazar
            </span>
            @endif
        </div>
        @if($linkedTournaments->isEmpty())
        <div class="p-8 text-center text-sm text-gray-400">
            Ningún torneo tiene <code>matchstat_tournament_id</code> configurado todavía.
            <br>
            <span class="text-xs">Edita un torneo y agrega el ID de Matchstat para activar el sync automático.</span>
        </div>
        @else
        <div class="divide-y divide-gray-100">
            @foreach($linkedTournaments as $t)
            <div class="flex items-center gap-4 px-6 py-3">
                <div class="w-10 h-10 rounded-xl bg-gradient-to-br {{ $t->type === 'GrandSlam' ? 'from-yellow-400 to-orange-500' : (str_starts_with($t->type, 'ATP') ? 'from-tc-primary to-blue-700' : 'from-purple-500 to-pink-500') }} flex items-center justify-center text-white font-black shrink-0">
                    {{ substr($t->type, 0, 1) }}
                </div>
                <div class="flex-1 min-w-0">
                    <div class="font-bold text-sm truncate">{{ $t->name }}</div>
                    <div class="text-[10px] text-gray-400">
                        Matchstat ID: <code class="font-mono">{{ $t->matchstat_tournament_id }}</code>
                        · Última: {{ $t->last_synced_at?->diffForHumans() ?? 'nunca' }}
                    </div>
                </div>
                <form method="POST" action="{{ route('admin.api-sync.live') }}">
                    @csrf
                    <input type="hidden" name="tournament_id" value="{{ $t->id }}">
                    <button type="submit" class="px-3 py-1.5 text-xs font-bold text-tc-primary bg-tc-primary/10 hover:bg-tc-primary/20 rounded-lg transition">Sync</button>
                </form>
            </div>
            @endforeach
        </div>
        @endif
    </div>

    {{-- Setup help --}}
    <details class="bg-gray-50 rounded-2xl border border-gray-200">
        <summary class="px-6 py-4 cursor-pointer font-bold text-gray-700 select-none">
            ¿Cómo configurar el cron en el servidor?
        </summary>
        <div class="px-6 pb-6 text-sm text-gray-600 space-y-2">
            <p>En tu hosting (Hostinger / cPanel / VPS), agrega esta línea al crontab:</p>
            <pre class="bg-gray-900 text-green-400 p-3 rounded-lg text-xs overflow-x-auto"># Laravel scheduler — corre cada minuto
* * * * * cd /home/user/domains/tudominio.com/public_html && php artisan schedule:run >> /dev/null 2>&1</pre>
            <p class="text-xs text-gray-500">Una vez configurado, el sistema correrá los syncs definidos en <code>routes/console.php</code> automáticamente. Para Hostinger: Panel → Cron Jobs → Crear nuevo cron job → frecuencia "cada minuto".</p>
        </div>
    </details>

</div>
@endsection
