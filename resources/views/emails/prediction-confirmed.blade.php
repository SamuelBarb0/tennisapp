<x-mail-layout preview="Tu predicción de {{ $tournament->name }} quedó guardada">
    <div style="display:inline-block; padding:6px 14px; background:#dcfce7; color:#166534; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
        ✓ Predicción guardada
    </div>

    <h1 style="margin:0 0 12px 0; color:#0f3460; font-size:24px; font-weight:800;">
        ¡Tu bracket está listo!
    </h1>

    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }},
    </p>

    <p style="margin:0 0 16px 0;">
        Confirmamos que tu predicción para <strong>{{ $tournament->name }}</strong> quedó guardada correctamente.
    </p>

    @if($champion)
        <div style="background:linear-gradient(135deg, #0f3460 0%, #1e3a8a 100%); border-radius:12px; padding:24px; margin:20px 0; text-align:center; color:#ffffff;">
            <div style="font-size:11px; font-weight:800; letter-spacing:1px; color:#fcd34d; text-transform:uppercase; margin-bottom:8px;">
                Tu campeón
            </div>
            <div style="font-size:22px; font-weight:800; color:#ffffff;">
                🏆 {{ $champion }}
            </div>
        </div>
    @endif

    <p style="margin:0 0 16px 0;">
        Vamos a ir actualizando tus puntos partido a partido. Puedes revisar tu progreso en cualquier momento.
    </p>

    <x-mail-button :url="route('tournaments.show', $tournament)">Ver mi bracket</x-mail-button>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        ¡Mucha suerte!
    </p>
</x-mail-layout>
