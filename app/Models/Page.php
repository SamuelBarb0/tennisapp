<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'slug', 'title', 'content', 'meta_description', 'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    /** Default content for the four built-in pages (seeded on first access). */
    public const DEFAULTS = [
        'reglas' => [
            'title' => 'Reglas del juego',
            'content' => '<h2>Cómo funciona Tennis Challenge</h2>
<p>Predice los resultados de los torneos ATP y WTA más importantes del mundo y compite con otros usuarios por premios.</p>
<h3>Cómo predecir</h3>
<ol>
<li>Selecciona un torneo activo desde la página principal.</li>
<li>Arma tu bracket completo desde la primera ronda hasta la final.</li>
<li>Guarda tu bracket antes de que comience el primer partido.</li>
</ol>
<h3>Puntos</h3>
<p>Ganas puntos por cada acierto. Las rondas avanzadas (cuartos, semifinales, final) valen más que las primeras rondas.</p>',
        ],
        'terminos' => [
            'title' => 'Términos y condiciones',
            'content' => '<h2>Términos y condiciones de uso</h2>
<p>Al utilizar Tennis Challenge aceptas estos términos.</p>
<h3>1. Uso de la plataforma</h3>
<p>Tennis Challenge es una plataforma de pronósticos de tenis con fines de entretenimiento.</p>
<h3>2. Registro</h3>
<p>Para participar debes ser mayor de 18 años y proporcionar información veraz.</p>
<h3>3. Pagos y premios</h3>
<p>Algunos torneos requieren pago para participar. Los premios se entregan según lo anunciado.</p>',
        ],
        'privacidad' => [
            'title' => 'Política de privacidad',
            'content' => '<h2>Política de privacidad</h2>
<p>Protegemos tus datos personales según la ley colombiana de protección de datos.</p>
<h3>Datos que recopilamos</h3>
<ul>
<li>Nombre, apellido y correo electrónico</li>
<li>Ciudad y país de residencia</li>
<li>Celular para notificaciones</li>
</ul>
<h3>Uso de la información</h3>
<p>Usamos tus datos solo para operar la plataforma y enviarte información sobre torneos.</p>',
        ],
        'contacto' => [
            'title' => 'Contacto',
            // The contact data (email, phone, socials, address) lives in
            // /admin/settings — the contact page template injects them
            // automatically. This editable HTML is just the intro copy.
            'content' => '<p>¿Tienes preguntas, sugerencias o problemas con tu cuenta? Estamos aquí para ayudarte.</p>
<p>Puedes contactarnos por cualquiera de los canales abajo. Respondemos típicamente en menos de 24 horas hábiles.</p>',
        ],
    ];

    /** Find or create a default page so admin always has a row to edit. */
    public static function findOrSeed(string $slug): ?self
    {
        $defaults = self::DEFAULTS[$slug] ?? null;
        if (!$defaults) return self::where('slug', $slug)->first();

        return self::firstOrCreate(
            ['slug' => $slug],
            [
                'title'        => $defaults['title'],
                'content'      => $defaults['content'],
                'is_published' => true,
            ],
        );
    }
}
