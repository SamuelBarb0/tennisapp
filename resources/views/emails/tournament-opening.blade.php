<x-mail-layout preview="¡Ya puedes predecir {{ $tournament->name }}!">
    <div style="display:inline-block; padding:6px 14px; background:#fef9c3; color:#854d0e; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
        🎾 Bracket disponible
    </div>

    <h1 style="margin:0 0 12px 0; color:#0f3460; font-size:24px; font-weight:800;">
        ¡Ya puedes predecir {{ $tournament->name }}!
    </h1>

    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }},
    </p>

    <p style="margin:0 0 16px 0;">
        El bracket de <strong>{{ $tournament->name }}</strong> ya está listo. Es el momento de armar tu pronóstico ronda por ronda y competir por los puntos.
    </p>

    <div style="background:#f9fafb; border-radius:12px; padding:18px 20px; margin:20px 0; border-left:4px solid #0f3460;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr>
                <td style="padding:4px 0; font-size:13px; color:#666;">📅 Fechas</td>
                <td style="padding:4px 0; font-size:13px; font-weight:600; color:#1a1a1a; text-align:right;">
                    {{ $tournament->start_date?->format('d M') }} – {{ $tournament->end_date?->format('d M Y') }}
                </td>
            </tr>
            @if($tournament->surface)
            <tr>
                <td style="padding:4px 0; font-size:13px; color:#666;">🎾 Superficie</td>
                <td style="padding:4px 0; font-size:13px; font-weight:600; color:#1a1a1a; text-align:right;">{{ $tournament->surface }}</td>
            </tr>
            @endif
            @if($tournament->city || $tournament->country)
            <tr>
                <td style="padding:4px 0; font-size:13px; color:#666;">📍 Lugar</td>
                <td style="padding:4px 0; font-size:13px; font-weight:600; color:#1a1a1a; text-align:right;">
                    {{ collect([$tournament->city, $tournament->country])->filter()->implode(', ') }}
                </td>
            </tr>
            @endif
            @if($tournament->is_premium && $tournament->price > 0)
            <tr>
                <td style="padding:4px 0; font-size:13px; color:#666;">💎 Acceso</td>
                <td style="padding:4px 0; font-size:13px; font-weight:600; color:#1a1a1a; text-align:right;">
                    Premium · ${{ number_format($tournament->price, 0, ',', '.') }} COP
                </td>
            </tr>
            @endif
        </table>
    </div>

    <p style="margin:0 0 8px 0;"><strong>Tip:</strong> los puntos se reparten ronda por ronda. Acertar las semifinales y la final vale mucho más que las primeras rondas.</p>

    <x-mail-button :url="route('tournaments.show', $tournament)">Predecir ahora</x-mail-button>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        ¡Que tengas suerte!
    </p>
</x-mail-layout>
