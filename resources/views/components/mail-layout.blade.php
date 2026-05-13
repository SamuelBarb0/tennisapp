@props(['preview' => null])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tennis Challenge</title>
    @if($preview)
        <style>.preview-text{display:none!important;visibility:hidden;opacity:0;color:transparent;height:0;width:0;overflow:hidden;mso-hide:all;}</style>
    @endif
</head>
<body style="margin:0; padding:0; background:#f5f5f7; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; color:#1a1a1a;">
    @if($preview)
        <div class="preview-text">{{ $preview }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f5f5f7;">
        <tr>
            <td align="center" style="padding:32px 16px;">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width:600px; width:100%; background:#ffffff; border-radius:16px; overflow:hidden; box-shadow:0 4px 24px rgba(0,0,0,0.06);">
                    {{-- Header --}}
                    <tr>
                        <td align="center" style="background:#0f3460; padding:32px 24px;">
                            <img src="{{ asset('images/image-removebg-preview.png') }}" alt="Tennis Challenge" height="56" style="display:block; height:56px; margin:0 auto;">
                        </td>
                    </tr>

                    {{-- Body --}}
                    <tr>
                        <td style="padding:36px 32px; line-height:1.6; font-size:15px; color:#333;">
                            {{ $slot }}
                        </td>
                    </tr>

                    {{-- Footer --}}
                    <tr>
                        <td align="center" style="background:#f9fafb; padding:24px 32px; border-top:1px solid #eee; font-size:12px; color:#888;">
                            <p style="margin:0 0 8px 0;">
                                <a href="{{ url('/') }}" style="color:#0f3460; text-decoration:none; font-weight:600;">tennischallenge.com.co</a>
                            </p>
                            <p style="margin:0; color:#aaa;">&copy; {{ date('Y') }} Tennis Challenge. Todos los derechos reservados.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
