<x-mail-layout preview="¡Quedan {{ $hoursLeft }} horas para predecir {{ $tournament->name }}!">
    <div style="display:inline-block; padding:6px 14px; background:#fee2e2; color:#991b1b; border-radius:9999px; font-size:11px; font-weight:800; letter-spacing:0.5px; text-transform:uppercase; margin-bottom:16px;">
        ⏰ Última oportunidad
    </div>

    <h1 style="margin:0 0 12px 0; color:#0f3460; font-size:24px; font-weight:800;">
        Quedan {{ $hoursLeft }} horas
    </h1>

    <p style="margin:0 0 16px 0;">
        Hola {{ $user->name }},
    </p>

    <p style="margin:0 0 16px 0;">
        Te queda <strong>poco tiempo</strong> para enviar tu bracket de <strong>{{ $tournament->name }}</strong>. Una vez empiece el primer partido, las predicciones se cierran.
    </p>

    <div style="background:#fef2f2; border-radius:12px; padding:20px; margin:20px 0; text-align:center;">
        <div style="font-size:48px; font-weight:900; color:#dc2626; line-height:1;">{{ $hoursLeft }}h</div>
        <div style="font-size:13px; color:#7f1d1d; margin-top:6px; font-weight:600;">para que cierre el torneo</div>
    </div>

    <p style="margin:0 0 16px 0;">
        Cierra el {{ $closesAt->format('d M Y') }} a las {{ $closesAt->format('H:i') }}.
    </p>

    <x-mail-button :url="route('tournaments.show', $tournament)">Hacer mi predicción</x-mail-button>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        Si ya completaste tu bracket, ignora este correo.
    </p>
</x-mail-layout>
