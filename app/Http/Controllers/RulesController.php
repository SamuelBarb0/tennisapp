<?php

namespace App\Http\Controllers;

use App\Models\Setting;

class RulesController extends Controller
{
    public function index()
    {
        $rules = Setting::get('rules_content', $this->defaultRules());
        return view('rules.index', compact('rules'));
    }

    private function defaultRules(): string
    {
        return <<<'HTML'
<h2>¿Cómo funciona Tennis Challenge?</h2>
<p>Tennis Challenge es una plataforma de pronósticos de tenis donde puedes predecir los ganadores de partidos en torneos profesionales y acumular puntos para canjear premios.</p>

<h3>Sistema de Puntos</h3>
<ul>
<li><strong>Pronóstico correcto:</strong> Cada vez que aciertas el ganador de un partido ganas puntos.</li>
<li><strong>Puntos por acierto:</strong> Se otorgan puntos fijos por cada predicción correcta en cualquier ronda del torneo.</li>
<li><strong>Ranking por torneo:</strong> Cada torneo tiene su propio ranking basado en los aciertos de los participantes.</li>
<li><strong>Ranking general:</strong> Tus puntos acumulados en todos los torneos determinan tu posición en el ranking general.</li>
</ul>

<h3>¿Cómo predecir?</h3>
<ol>
<li>Regístrate o inicia sesión en tu cuenta.</li>
<li>Ve a la sección de Torneos y elige un torneo activo.</li>
<li>Selecciona el ganador de cada partido disponible.</li>
<li>Espera a que el partido termine. Si acertaste, los puntos se acreditarán automáticamente.</li>
</ol>

<h3>Torneos Gratuitos y Premium</h3>
<ul>
<li><strong>Torneos gratuitos:</strong> Todos los usuarios pueden participar sin costo.</li>
<li><strong>Torneos premium:</strong> Pueden tener beneficios especiales.</li>
</ul>

<h3>Ranking</h3>
<p>El ranking se basa en los puntos acumulados. Cuantos más pronósticos aciertes, más alto será tu posición.</p>

<h3>Premios y Canjes</h3>
<ul>
<li>Acumula puntos y canjéalos por premios disponibles en la sección de Premios.</li>
<li>Cada premio tiene un costo en puntos y stock limitado.</li>
<li>Una vez canjeado, el equipo se pondrá en contacto contigo para la entrega.</li>
</ul>
HTML;
    }
}
