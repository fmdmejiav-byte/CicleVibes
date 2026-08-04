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
        Schema::create('bicicletas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->cascadeOnDelete();

            $table->foreignId('tipo_bicicleta_id')
                ->constrained('tipo_bicicletas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->foreignId('barrio_id')
                ->nullable()
                ->constrained('barrios')
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->string('marca');

            $table->string('modelo');

            $table->string('color');

            $table->string('numero_serie')->unique();

            $table->string('foto')->nullable();

            $table->enum('estado', [
                'Activa',
                'Robada',
                'Vendida',
            ])->default('Activa');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bicicletas');
    }
};
