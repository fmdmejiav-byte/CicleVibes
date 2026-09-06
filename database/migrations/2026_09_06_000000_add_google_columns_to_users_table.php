<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Los usuarios autenticados únicamente con Google no tienen
            // contraseña local, por lo que el campo pasa a ser opcional.
            $table->string('password')->nullable()->change();

            // Identificador público del usuario en Google (no se guardan
            // tokens ni secretos de Google).
            $table->string('google_id', 100)
                ->nullable()
                ->unique()
                ->after('password');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn('google_id');

            // Al revertir, se requiere contraseña de nuevo. Aquellos usuarios
            // que quedaran con contraseña nula romperían la restricción; se
            // completa un hash aleatorio imposible de usar como credencial.
            DB::table('users')
                ->whereNull('password')
                ->update(['password' => Str::random(60)]);

            $table->string('password')->nullable(false)->change();
        });
    }
};
