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
        Schema::create('esceanarios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 255);
            $table->string('estado', 20)->default('Activo');
            $table->text('descripcion');
            $table->decimal('versiones', 10, 1)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('esceanarios');
    }
};
