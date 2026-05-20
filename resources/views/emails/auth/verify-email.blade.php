<x-mail-layout preview="Verifica tu correo en Tennis Challenge para activar tu cuenta.">
    <h1 style="margin:0 0 16px 0; color:#0f3460; font-size:24px; font-weight:800;">
        ¡Verifica tu correo!
    </h1>

    <p style="margin:0 0 16px 0;">
        Hola{{ isset($user->name) ? ', ' . e($user->name) : '' }}. Gracias por unirte a <strong>Tennis Challenge</strong>.
        Para activar tu cuenta y empezar a predecir, confirma tu dirección de correo electrónico con el botón de abajo.
    </p>

    <x-mail-button :url="$url">Verificar mi correo</x-mail-button>

    <p style="margin:24px 0 0 0; color:#666; font-size:13px;">
        Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
        <a href="{{ $url }}" style="color:#0f3460; word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        Si tú no creaste una cuenta en Tennis Challenge, puedes ignorar este correo.
    </p>
</x-mail-layout>
