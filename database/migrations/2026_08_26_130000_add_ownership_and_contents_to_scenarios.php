<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('esceanarios', function (Blueprint $table) {
            $table->foreignId('owner_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
        });

        Schema::table('escenarios_users', function (Blueprint $table) {
            $table->string('access_level', 30)->default('collaborator')->after('user_id');
            $table->foreignId('invited_by')->nullable()->after('access_level')->constrained('users')->nullOnDelete();
            $table->unique(['escenario_id', 'user_id']);
        });

        Schema::create('escenario_contenidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('escenario_id')->constrained('esceanarios')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('modified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('nombre');
            $table->string('ruta');
            $table->string('tipo', 40)->default('archivo');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamano')->default(0);
            $table->decimal('version', 10, 1)->default(1);
            $table->string('estado', 30)->default('Disponible');
            $table->timestamps();
        });

        DB::table('escenarios_users')->orderBy('id')->get()->groupBy('escenario_id')->each(
            function ($members, $scenarioId) {
                $owner = $members->first();
                DB::table('esceanarios')->where('id', $scenarioId)->update(['owner_id' => $owner->user_id]);
                DB::table('escenarios_users')->where('id', $owner->id)->update([
                    'access_level' => 'owner',
                    'invited_by' => $owner->user_id,
                ]);
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('escenario_contenidos');

        Schema::table('escenarios_users', function (Blueprint $table) {
            $table->dropUnique(['escenario_id', 'user_id']);
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn('access_level');
        });

        Schema::table('esceanarios', function (Blueprint $table) {
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
