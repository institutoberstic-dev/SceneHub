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
        Schema::create('escenario_tecnologia', function (Blueprint $table) {
            $table->id();
            // La tabla de escenarios conserva el nombre histórico "esceanarios".
            $table->foreignId('escenario_id')->constrained('esceanarios')->cascadeOnDelete();
            // Una tecnología en uso no se borra: se desactiva (RN-06).
            $table->foreignId('tecnologia_id')->constrained('tecnologias')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['escenario_id', 'tecnologia_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escenario_tecnologia');
    }
};
