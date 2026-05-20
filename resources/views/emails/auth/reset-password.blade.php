<x-mail-layout preview="Solicitaste restablecer tu contraseña de Tennis Challenge.">
    <h1 style="margin:0 0 16px 0; color:#0f3460; font-size:24px; font-weight:800;">
        Restablece tu contraseña
    </h1>

    <p style="margin:0 0 16px 0;">
        Hola{{ isset($user->name) ? ', ' . e($user->name) : '' }}. Recibimos una solicitud para restablecer la contraseña
        de tu cuenta en <strong>Tennis Challenge</strong>. Para elegir una nueva, haz clic en el botón de abajo.
    </p>

    <x-mail-button :url="$url">Restablecer contraseña</x-mail-button>

    <p style="margin:16px 0 0 0; color:#666; font-size:13px;">
        Este enlace expira en <strong>{{ $expire }} minutos</strong>.
    </p>

    <p style="margin:16px 0 0 0; color:#666; font-size:13px;">
        Si el botón no funciona, copia y pega esta URL en tu navegador:<br>
        <a href="{{ $url }}" style="color:#0f3460; word-break:break-all;">{{ $url }}</a>
    </p>

    <p style="margin:16px 0 0 0; color:#888; font-size:13px;">
        Si tú no solicitaste el restablecimiento, puedes ignorar este correo y tu contraseña seguirá igual.
    </p>
</x-mail-layout>
