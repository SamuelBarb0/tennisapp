<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the profile fields the customer requested in the registration flow:
 * apellido, ciudad, país (ISO-2), fecha de nacimiento. The phone column
 * already exists from the legacy schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('last_name')->nullable()->after('name');
            $table->string('city')->nullable()->after('phone');
            $table->string('country_code', 2)->nullable()->after('city');
            $table->date('birth_date')->nullable()->after('country_code');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'city', 'country_code', 'birth_date']);
        });
    }
};
