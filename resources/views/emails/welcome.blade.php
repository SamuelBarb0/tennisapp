<x-mail-layout preview="¡Bienvenido a Tennis Challenge! Tu cuenta ya está lista.">
    <h1 style="margin:0 0 16px 0; color:#0f3460; font-size:24px; font-weight:800;">
        ¡Bienvenido, {{ $user->name }}!
    </h1>

    <p style="margin:0 0 16px 0;">
        Acabas de unirte a <strong>Tennis Challenge</strong>, la plataforma donde compites prediciendo los brackets de los torneos ATP y WTA más importantes del mundo.
    </p>

    <p style="margin:0 0 12px 0;"><strong>¿Qué sigue?</strong></p>

    <ul style="margin:0 0 24px 0; padding-left:20px;">
        <li style="margin-bottom:8px;">Explora los torneos disponibles para predecir.</li>
        <li style="margin-bottom:8px;">Llena tu bracket completo antes del primer partido.</li>
        <li style="margin-bottom:8px;">Sigue tus puntos y compite en el ranking de cada torneo.</li>
    </ul>

    <x-mail-button :url="url('/')">Empieza a predecir</x-mail-button>

    <p style="margin:24px 0 0 0; color:#888; font-size:13px;">
        ¡Mucha suerte y que gane el mejor pronosticador!
    </p>
</x-mail-layout>
