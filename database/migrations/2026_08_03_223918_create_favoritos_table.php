<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
{
    Schema::create('favoritos', function (Blueprint $table) {
        $table->id();

        // Usuario que guarda la ruta
        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

        // Ruta guardada como favorita
        $table->foreignId('ruta_id')
            ->constrained('rutas')
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

        $table->timestamps();

        // Evita que un usuario guarde la misma ruta dos veces
        $table->unique(['user_id', 'ruta_id']);
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('favoritos');
    }
};
