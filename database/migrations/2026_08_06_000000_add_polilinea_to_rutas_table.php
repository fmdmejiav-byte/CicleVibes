<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Polilínea codificada de la ruta calculada (Google Directions API).
     */
    public function up(): void
    {
        Schema::table('rutas', function (Blueprint $table) {
            $table->text('polilinea')->nullable()->after('duracion');
        });
    }

    public function down(): void
    {
        Schema::table('rutas', function (Blueprint $table) {
            $table->dropColumn('polilinea');
        });
    }
};
