<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tokens de SOLO LECTURA para la app de escritorio «Puente de datos».
 * Solo se guarda el SHA-256 del token: el token en claro se muestra una vez
 * al crearlo (php artisan puente:token-crear) y nunca se puede recuperar.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('puente_tokens')) {
            return;
        }
        Schema::create('puente_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->char('token_hash', 64)->unique();
            $table->string('alcance', 20)->default('lectura');
            $table->dateTime('expira_en')->nullable();
            $table->dateTime('ultimo_uso_en')->nullable();
            $table->dateTime('revocado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('puente_tokens');
    }
};
