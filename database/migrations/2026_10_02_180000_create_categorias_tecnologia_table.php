<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categorías para agrupar el catálogo de tecnologías (generación de energía,
 * tratamiento de agua, hidrógeno y amoniaco, valorización de residuos).
 * Igual que las tecnologías, cada categoría tiene un código estable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias_tecnologia', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 100)->unique();
            $table->string('nombre', 255);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();
        });

        Schema::table('tecnologias', function (Blueprint $table) {
            // Nullable para no romper tecnologías existentes antes de ejecutar el seeder.
            $table->foreignId('categoria_id')->nullable()->after('nombre')
                ->constrained('categorias_tecnologia')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('tecnologias', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categoria_id');
        });
        Schema::dropIfExists('categorias_tecnologia');
    }
};
