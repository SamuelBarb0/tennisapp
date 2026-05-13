@props(['url'])

<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px auto;">
    <tr>
        <td style="border-radius:9999px; background:#0f3460;">
            <a href="{{ $url }}" style="display:inline-block; padding:14px 32px; color:#ffffff; text-decoration:none; font-weight:700; font-size:14px; letter-spacing:0.3px; border-radius:9999px;">
                {{ $slot }}
            </a>
        </td>
    </tr>
</table>
