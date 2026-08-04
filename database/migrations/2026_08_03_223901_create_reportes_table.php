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
    Schema::create('reportes', function (Blueprint $table) {
        $table->id();

        // Usuario que realiza el reporte
        $table->foreignId('user_id')
            ->constrained()
            ->cascadeOnUpdate()
            ->cascadeOnDelete();

        // Tipo de reporte
        $table->foreignId('tipo_reporte_id')
            ->constrained('tipo_reportes')
            ->cascadeOnUpdate()
            ->restrictOnDelete();

        // Barrio donde ocurre el incidente
        $table->foreignId('barrio_id')
            ->constrained('barrios')
            ->cascadeOnUpdate()
            ->restrictOnDelete();

        // Información del reporte
        $table->string('titulo', 150);

        $table->text('descripcion');

        $table->string('direccion', 255);

        // Coordenadas (opcional)
        $table->decimal('latitud', 10, 8)->nullable();

        $table->decimal('longitud', 11, 8)->nullable();

        // Foto del incidente (opcional)
        $table->string('imagen')->nullable();

        // Estado del reporte
        $table->enum('estado', [
            'Pendiente',
            'Verificado',
            'Resuelto'
        ])->default('Pendiente');

        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reportes');
    }
};
