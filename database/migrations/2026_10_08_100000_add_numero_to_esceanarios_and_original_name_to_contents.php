<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Número de escenario (define el nombre «resultados escenario N») y nombre con el
 * que se subió cada archivo antes de renombrarlo a su nombre canónico.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('esceanarios', 'numero')) {
            Schema::table('esceanarios', function (Blueprint $table) {
                $table->unsignedInteger('numero')->nullable()->after('id');
            });
        }
        if (! Schema::hasColumn('escenario_contenidos', 'nombre_original')) {
            Schema::table('escenario_contenidos', function (Blueprint $table) {
                $table->string('nombre_original')->nullable()->after('nombre');
            });
        }

        $this->assignNumbers();

        Schema::table('esceanarios', function (Blueprint $table) {
            $table->unique('numero');
        });
    }

    /**
     * Respeta el número que ya trae el nombre («Escenario 1» ⇒ 1) cuando está libre;
     * el resto recibe el menor número disponible, en orden de creación.
     */
    private function assignNumbers(): void
    {
        $scenarios = DB::table('esceanarios')->orderBy('id')->get(['id', 'nombre', 'numero']);
        $used = $scenarios->pluck('numero')->filter()->map(fn ($value) => (int) $value)->flip()->all();
        $pending = [];

        foreach ($scenarios as $scenario) {
            if ($scenario->numero) {
                continue;
            }
            $number = $this->numberInName((string) $scenario->nombre);
            if ($number !== null && ! isset($used[$number])) {
                $used[$number] = true;
                DB::table('esceanarios')->where('id', $scenario->id)->update(['numero' => $number]);

                continue;
            }
            $pending[] = $scenario->id;
        }

        $next = 1;
        foreach ($pending as $id) {
            while (isset($used[$next])) {
                $next++;
            }
            $used[$next] = true;
            DB::table('esceanarios')->where('id', $id)->update(['numero' => $next]);
        }
    }

    /** «Escenario 1», «Escenario_2», «Escenario Nº 3», «esc4» ⇒ número; sin palabra clave ⇒ null. */
    private function numberInName(string $name): ?int
    {
        $text = Str::lower(Str::ascii($name));
        $text = preg_replace('/(?<=[a-z])(?=\d)|(?<=\d)(?=[a-z])/', ' ', $text);
        $tokens = preg_split('/[^a-z0-9]+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($tokens as $index => $token) {
            if (! in_array($token, ['escenario', 'esc', 'caso', 'scenario'], true)) {
                continue;
            }
            for ($next = $index + 1; $next < count($tokens); $next++) {
                if (in_array($tokens[$next], ['no', 'n', 'nro', 'num', 'numero', 'o', 'de', 'del', 'el'], true)) {
                    continue;
                }
                if (ctype_digit($tokens[$next]) && strlen($tokens[$next]) <= 4 && (int) $tokens[$next] > 0) {
                    return (int) $tokens[$next];
                }
                break;
            }
        }

        return null;
    }

    public function down(): void
    {
        Schema::table('esceanarios', function (Blueprint $table) {
            $table->dropUnique(['numero']);
            $table->dropColumn('numero');
        });
        Schema::table('escenario_contenidos', function (Blueprint $table) {
            $table->dropColumn('nombre_original');
        });
    }
};
