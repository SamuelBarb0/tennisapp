<x-mail-layout preview="{{ $tournament->name }} terminó · Tu posición: #{{ $position }}">
    @if($position === 1)
        <div style="display:inline-block; padding:6px 14px; background:#fef3c7; color:#854d0e; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
            🏆 ¡Campeón!
        </div>
    @elseif($position <= 3)
        <div style="display:inline-block; padding:6px 14px; background:#fef9c3; color:#854d0e; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
            🥇 Top 3
        </div>
    @else
        <div style="display:inline-block; padding:6px 14px; background:#e0e7ff; color:#3730a3; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
            🎾 Torneo cerrado
        </div>
    @endif

    <h1 style="margin:0 0 12px 0; color:#0f3460; font-size:24px; font-weight:800;">
        {{ $tournament->name }} terminó
    </h1>

    <p style="margin:0 0 20px 0;">
        Hola {{ $user->name }}, así te fue:
    </p>

    {{-- Stats grid --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin:20px 0;">
        <tr>
            <td width="33%" align="center" style="padding:18px 8px; background:#0f3460; border-radius:12px 0 0 12px; color:#ffffff;">
                <div style="font-size:11px; font-weight:700; letter-spacing:0.5px; opacity:0.8; text-transform:uppercase;">Posición</div>
                <div style="font-size:32px; font-weight:900; margin-top:4px;">#{{ $position }}</div>
                <div style="font-size:10px; opacity:0.8; margin-top:2px;">de {{ $totalParticipants }}</div>
            </td>
            <td width="34%" align="center" style="padding:18px 8px; background:#1e3a8a; color:#ffffff;">
                <div style="font-size:11px; font-weight:700; letter-spacing:0.5px; opacity:0.8; text-transform:uppercase;">Puntos</div>
                <div style="font-size:32px; font-weight:900; margin-top:4px; color:#fcd34d;">{{ $points }}</div>
                <div style="font-size:10px; opacity:0.8; margin-top:2px;">en este torneo</div>
            </td>
            <td width="33%" align="center" style="padding:18px 8px; background:#1e40af; border-radius:0 12px 12px 0; color:#ffffff;">
                <div style="font-size:11px; font-weight:700; letter-spacing:0.5px; opacity:0.8; text-transform:uppercase;">Aciertos</div>
                <div style="font-size:32px; font-weight:900; margin-top:4px;">{{ $correctPicks }}</div>
                <div style="font-size:10px; opacity:0.8; margin-top:2px;">de {{ $totalPicks }} picks</div>
            </td>
        </tr>
    </table>

    @if($prize)
        <div style="background:linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); border-radius:12px; padding:20px; margin:20px 0; text-align:center;">
            <div style="font-size:11px; font-weight:800; letter-spacing:1px; color:#7c2d12; text-transform:uppercase; margin-bottom:6px;">
                🎁 Tu premio
            </div>
            <div style="font-size:18px; font-weight:800; color:#7c2d12;">
                {{ $prize }}
            </div>
            <p style="margin:10px 0 0 0; font-size:12px; color:#7c2d12;">
                Te contactaremos pronto para coordinar la entrega.
            </p>
        </div>
    @endif

    <p style="margin:16px 0;">
        Ya está disponible el siguiente torneo. ¡Es hora de revancha!
    </p>

    <x-mail-button :url="url('/')">Ver próximos torneos</x-mail-button>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        Gracias por participar.
    </p>
</x-mail-layout>
