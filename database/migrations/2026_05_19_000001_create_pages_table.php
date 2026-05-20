<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Editable CMS pages. The admin can edit content for:
 *   - reglas      (Reglas del juego)
 *   - terminos    (Términos y condiciones)
 *   - privacidad  (Política de privacidad)
 *   - contacto    (Contacto)
 *
 * `slug` is the URL slug — used as the route segment (/reglas, /terminos…).
 * `content` is HTML rendered by the visual editor.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->longText('content')->nullable();
            $table->string('meta_description', 500)->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
