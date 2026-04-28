<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            // Admin flag: when true, this tournament shows up under "Próximos torneos
            // a predecir" on the home page. Defaults false so admins opt in explicitly.
            $table->boolean('featured_on_home')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            $table->dropColumn('featured_on_home');
        });
    }
};
