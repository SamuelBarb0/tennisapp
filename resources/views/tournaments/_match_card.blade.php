@php
    $userPick = $userPredictions[$match->id] ?? null;
    $predFull = $userPredictionsFull[$match->id] ?? null;
    $isPending = $match->status === 'pending';
    $isLive = $match->status === 'live';
    $isFinished = $match->status === 'finished';
    $isCancelled = $match->status === 'cancelled';
    $p1Won = $match->winner_id && $match->winner_id == $match->player1_id;
    $p2Won = $match->winner_id && $match->winner_id == $match->player2_id;
    $predCorrect = $predFull && $predFull->is_correct === 1;
    $predWrong = $predFull && $predFull->is_correct === 0;
    $predPoints = $predFull ? $predFull->points_earned : 0;
@endphp

<div x-data="{
    showPick: false, saving: false, saved: {{ $userPick ? 'true' : 'false' }}, pickedId: {{ $userPick ?? 'null' }},
    resolved: {{ ($predCorrect || $predWrong) ? 'true' : 'false' }},
    correct: {{ $predCorrect ? 'true' : 'false' }},
    earnedPts: {{ $predPoints }},
    showResult: false,
    async debugResolve(result) {
        const res = await fetch('{{ route('predictions.debug-resolve') }}', {
            method: 'POST',
            headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
            body: JSON.stringify({ match_id: {{ $match->id }}, result })
        });
        const data = await res.json();
        if (data.success) {
            this.resolved = true;
            this.correct = data.correct;
            this.earnedPts = data.points_earned;
            this.showResult = true;
            // Trigger fullscreen animation
            window.dispatchEvent(new CustomEvent('prediction-result', {
                detail: { correct: data.correct, points: data.points_earned, total: data.total_points }
            }));
            setTimeout(() => { this.showResult = false; }, 3000);
        }
    }
}">
    <div
        {!! $isPending && auth()->check() ? 'x-on:click="if(!resolved) showPick = !showPick"' : '' !!}
        class="match-card rounded-lg overflow-hidden border transition-all"
        :class="{
            'bg-gray-50 opacity-60': {{ $isCancelled ? 'true' : 'false' }},
            'border-red-300 ring-1 ring-red-100': {{ $isLive ? 'true' : 'false' }},
            'border-green-400 ring-2 ring-green-100': resolved && correct,
            'border-red-300 ring-1 ring-red-100': resolved && !correct && !{{ $isLive ? 'true' : 'false' }},
            'border-gray-200': !resolved && !{{ $isLive ? 'true' : 'false' }} && !{{ $isCancelled ? 'true' : 'false' }},
            'ring-2 ring-tc-accent !border-tc-accent': showPick,
            'bg-white': !{{ $isCancelled ? 'true' : 'false' }},
            'clickable': {{ ($isPending && auth()->check()) ? 'true' : 'false' }} && !resolved
        }"
    >
        {{-- Top badge --}}
        @if($isCancelled)
        <div class="bg-gray-400 text-white text-[8px] font-bold text-center py-0.5 tracking-widest">CANCELADO</div>
        @elseif($isLive)
        <div class="bg-red-500 text-white text-[8px] font-bold text-center py-0.5 tracking-widest flex items-center justify-center gap-1">
            <span class="w-1 h-1 bg-white rounded-full animate-pulse"></span> EN VIVO
        </div>
        @endif

        {{-- Result badge (animated) --}}
        <template x-if="resolved && correct">
            <div class="bg-green-500 text-white text-[8px] font-bold text-center py-0.5 tracking-widest flex items-center justify-center gap-1"
                 :class="showResult ? 'animate-pulse' : ''">
                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                ACERTASTE +<span x-text="earnedPts"></span>PTS
            </div>
        </template>
        <template x-if="resolved && !correct">
            <div class="bg-red-400 text-white text-[8px] font-bold text-center py-0.5 tracking-widest flex items-center justify-center gap-1">
                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"/></svg>
                FALLASTE
            </div>
        </template>

        {{-- Pending prediction badge --}}
        <template x-if="saved && !showPick && !resolved && !{{ $isFinished ? 'true' : 'false' }}">
            <div class="bg-tc-accent/15 text-tc-primary text-[8px] font-bold text-center py-0.5 tracking-widest">TU PRONÓSTICO</div>
        </template>

        {{-- Player 1 --}}
        <div class="pr {{ $isCancelled ? 'l' : ($isFinished ? ($p1Won ? 'w' : 'l') : ($isLive ? 'lv' : 'n')) }}"
             :class="{
                'pk': !{{ ($isFinished || $isCancelled) ? 'true' : 'false' }} && !resolved && pickedId === {{ $match->player1_id }},
                'w': resolved && {{ $match->player1_id }} === {{ $match->player1_id }} && correct && pickedId === {{ $match->player1_id }},
             }">
            <span class="text-[9px] font-mono w-4 text-right opacity-40 shrink-0">{{ $match->player1->ranking ?? '' }}</span>
            <img src="{{ $match->player1->flag_url }}" alt="" class="w-4 h-3 rounded-[2px] object-cover shrink-0" loading="lazy">
            <span class="font-semibold truncate flex-1">{{ strtoupper($match->player1->name) }}</span>
            @if($match->score)
                @foreach(explode(' ', $match->score) as $set)
                    @php $s = explode('-', $set); @endphp
                    <span class="ss text-[10px] font-mono font-bold w-3 text-center">{{ $s[0] ?? '' }}</span>
                @endforeach
            @endif
            @if($isFinished && $userPick === $match->player1_id)
                <span class="w-2 h-2 rounded-full {{ $predCorrect ? 'bg-green-400' : 'bg-red-400' }} shrink-0"></span>
            @endif
            <template x-if="pickedId === {{ $match->player1_id }} && !resolved && !{{ ($isFinished || $isCancelled) ? 'true' : 'false' }}">
                <span class="w-2 h-2 rounded-full bg-tc-accent shrink-0"></span>
            </template>
        </div>

        <div class="h-px bg-gray-100"></div>

        {{-- Player 2 --}}
        <div class="pr {{ $isCancelled ? 'l' : ($isFinished ? ($p2Won ? 'w' : 'l') : ($isLive ? 'lv' : 'n')) }}"
             :class="{
                'pk': !{{ ($isFinished || $isCancelled) ? 'true' : 'false' }} && !resolved && pickedId === {{ $match->player2_id }},
             }">
            <span class="text-[9px] font-mono w-4 text-right opacity-40 shrink-0">{{ $match->player2->ranking ?? '' }}</span>
            <img src="{{ $match->player2->flag_url }}" alt="" class="w-4 h-3 rounded-[2px] object-cover shrink-0" loading="lazy">
            <span class="font-semibold truncate flex-1">{{ strtoupper($match->player2->name) }}</span>
            @if($isCancelled)
                <span class="text-[7px] font-bold text-gray-400 bg-gray-200 px-1.5 py-0.5 rounded">CANC</span>
            @elseif($match->score)
                @foreach(explode(' ', $match->score) as $set)
                    @php $s = explode('-', $set); @endphp
                    <span class="ss text-[10px] font-mono font-bold w-3 text-center">{{ $s[1] ?? '' }}</span>
                @endforeach
            @endif
            @if($isFinished && $userPick === $match->player2_id)
                <span class="w-2 h-2 rounded-full {{ $predCorrect ? 'bg-green-400' : 'bg-red-400' }} shrink-0"></span>
            @endif
            <template x-if="pickedId === {{ $match->player2_id }} && !resolved && !{{ ($isFinished || $isCancelled) ? 'true' : 'false' }}">
                <span class="w-2 h-2 rounded-full bg-tc-accent shrink-0"></span>
            </template>
        </div>

        {{-- Date for pending --}}
        @if($isPending)
        <div class="text-center py-0.5 bg-gray-50 border-t border-gray-100">
            <span class="text-[8px] text-gray-400">{{ $match->scheduled_at->format('d M, H:i') }}</span>
        </div>
        @endif
    </div>

    {{-- Prediction picker + debug buttons --}}
    @if($isPending && auth()->check())
    <div x-show="showPick && !resolved" x-collapse x-on:click.outside="showPick = false" class="mt-1.5">
        <div class="rounded-lg border-2 border-tc-accent shadow-lg overflow-hidden bg-white">
            <div class="bg-tc-primary px-3 py-1.5">
                <span class="text-[10px] font-bold text-tc-accent uppercase tracking-wider">¿Quién gana?</span>
            </div>
            <div class="p-1.5 space-y-1">
                @foreach([
                    ['player' => $match->player1, 'id' => $match->player1_id],
                    ['player' => $match->player2, 'id' => $match->player2_id]
                ] as $opt)
                <button
                    x-on:click.stop="saving = true; fetch('{{ route('predictions.store') }}', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json'},
                        body: JSON.stringify({match_id: {{ $match->id }}, predicted_winner_id: {{ $opt['id'] }}})
                    }).then(r => r.json()).then(d => { if(d.success) { pickedId = {{ $opt['id'] }}; saved = true; } saving = false; }).catch(() => { saving = false; })"
                    :disabled="saving"
                    class="w-full flex items-center gap-2 px-2.5 py-2 rounded-md transition-all text-left text-[11px]"
                    :class="pickedId === {{ $opt['id'] }} ? 'bg-tc-primary text-white' : 'bg-gray-50 hover:bg-tc-primary/5'"
                >
                    <img src="{{ $opt['player']->flag_url }}" alt="" class="w-4 h-3 rounded-[2px] object-cover shrink-0">
                    <span class="font-semibold flex-1 truncate">{{ $opt['player']->name }}</span>
                    <template x-if="pickedId === {{ $opt['id'] }}">
                        <svg class="w-3.5 h-3.5 text-tc-accent shrink-0" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                    </template>
                </button>
                @endforeach
            </div>

            {{-- Debug buttons --}}
            @if(config('app.debug'))
            <div class="flex gap-1 px-1.5 pb-1.5">
                <button x-on:click.stop="if(saved) debugResolve('win')"
                    class="flex-1 py-1.5 rounded text-[9px] font-bold text-center bg-green-500 text-white hover:bg-green-600 transition"
                    :class="!saved && 'opacity-30 cursor-not-allowed'">
                    GANAR
                </button>
                <button x-on:click.stop="if(saved) debugResolve('lose')"
                    class="flex-1 py-1.5 rounded text-[9px] font-bold text-center bg-red-500 text-white hover:bg-red-600 transition"
                    :class="!saved && 'opacity-30 cursor-not-allowed'">
                    PERDER
                </button>
            </div>
            @endif
        </div>
    </div>
    @endif
</div>
