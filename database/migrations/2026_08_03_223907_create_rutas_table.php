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
    Schema::create('rutas', function (Blueprint $table) {
        $table->id();

        // Barrio al que pertenece la ruta
        $table->foreignId('barrio_id')
            ->constrained('barrios')
            ->cascadeOnUpdate()
            ->restrictOnDelete();

        $table->string('nombre', 150);

        $table->string('origen');

        $table->string('destino');

        $table->decimal('distancia', 5, 2);

        $table->enum('dificultad', [
            'Baja',
            'Media',
            'Alta'
        ]);

        $table->enum('nivel_seguridad', [
            'Alto',
            'Medio',
            'Bajo'
        ]);

        $table->text('descripcion')->nullable();

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};
