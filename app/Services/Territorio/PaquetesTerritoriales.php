<?php

namespace App\Services\Territorio;

use App\Models\ArchivoTerritorial;
use App\Models\PaqueteTerritorial;
use App\Models\RegistroTerritorial;
use App\Models\User;
use App\Models\ValidacionTerritorial;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Flujo del paquete territorial: borrador → subida → validación automática →
 * revisión humana → publicación. Una versión publicada es inmutable: se puede
 * retirar, pero no borrar ni cambiar.
 */
class PaquetesTerritoriales
{
    public function __construct(private ValidadorTerritorial $validador) {}

    private function disco()
    {
        return Storage::disk(config('territorio.disco'));
    }

    public function registrar(PaqueteTerritorial $paquete, ?User $usuario, string $accion, string $detalle = ''): void
    {
        RegistroTerritorial::create(['paquete_id' => $paquete->id, 'user_id' => $usuario?->id, 'accion' => $accion, 'detalle' => Str::limit($detalle, 1000)]);
    }

    public function siguienteVersion(): string
    {
        $numeros = PaqueteTerritorial::query()->pluck('version')
            ->map(fn (string $version) => (int) preg_replace('/\D/', '', $version));

        return 'v'.str_pad((string) (($numeros->max() ?? 0) + 1), 2, '0', STR_PAD_LEFT);
    }

    public function crear(string $version, ?string $notas, User $usuario): PaqueteTerritorial
    {
        return DB::transaction(function () use ($version, $notas, $usuario) {
            $paquete = PaqueteTerritorial::create(['version' => $version, 'notas' => $notas, 'estado' => PaqueteTerritorial::BORRADOR, 'creado_por' => $usuario->id]);
            $this->registrar($paquete, $usuario, 'creado', "Borrador {$version} creado.");

            return $paquete;
        });
    }

    /** Guarda el archivo en el disco privado, calcula el SHA-256 y lo valida. */
    public function subirArchivo(PaqueteTerritorial $paquete, UploadedFile $archivo, array $datos, User $usuario): ArchivoTerritorial
    {
        $this->exigirEditable($paquete);
        $nombre = $this->nombreSeguro($archivo->getClientOriginalName());
        $carpeta = $paquete->carpeta().'/'.$datos['tipo'].'/'.now()->format('Ymd_His').'_'.Str::lower(Str::random(6));
        $ruta = $this->disco()->putFileAs($carpeta, $archivo, $nombre);
        if (! $ruta) {
            throw ValidationException::withMessages(['archivo' => 'No fue posible guardar el archivo en el servidor.']);
        }
        $absoluta = $this->disco()->path($ruta);

        try {
            return DB::transaction(function () use ($paquete, $archivo, $datos, $usuario, $nombre, $ruta, $absoluta) {
                $reemplazados = [];
                if (in_array($datos['tipo'], config('territorio.unicos'), true)) {
                    foreach ($paquete->archivos()->where('tipo', $datos['tipo'])->get() as $anterior) {
                        $reemplazados[] = $anterior->nombre_original;
                        $this->borrarArchivo($anterior);
                    }
                }
                $registro = ArchivoTerritorial::create([
                    'paquete_id' => $paquete->id,
                    'tipo' => $datos['tipo'],
                    'descripcion' => $datos['descripcion'] ?? null,
                    'capa' => $datos['capa'] ?? null,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'nombre' => $nombre,
                    'ruta' => $ruta,
                    'tamano' => filesize($absoluta),
                    'mime_type' => mime_content_type($absoluta) ?: null,
                    'sha256' => hash_file('sha256', $absoluta),
                    'sha256_declarado' => isset($datos['sha256_declarado']) ? strtolower($datos['sha256_declarado']) : null,
                    'crs' => in_array($datos['tipo'], ['sitio_json', 'escena_glb', 'otro'], true) ? null : 'EPSG:'.config('territorio.srid'),
                    'fuente' => $datos['fuente'] ?? null,
                    'licencia' => $datos['licencia'] ?? null,
                    'fecha_captura' => $datos['fecha_captura'] ?? null,
                    'es_sensible' => (bool) ($datos['es_sensible'] ?? false),
                    'subido_por' => $usuario->id,
                ]);
                foreach ($this->validador->validarArchivo($registro, $absoluta) as $validacion) {
                    if (isset($validacion['capa']) && ! $registro->capa) {
                        $registro->update(['capa' => $validacion['capa']]);
                    }
                    unset($validacion['capa']);
                    ValidacionTerritorial::create([...$validacion, 'paquete_id' => $paquete->id, 'archivo_id' => $registro->id]);
                }
                if ($paquete->estado === PaqueteTerritorial::VALIDADO) {
                    $paquete->update(['estado' => PaqueteTerritorial::BORRADOR]);
                }
                $this->limpiarValidacionesDePaquete($paquete);
                $detalle = "{$registro->tipo}: {$registro->nombre_original} (sha256 ".substr($registro->sha256, 0, 12).'…)';
                $this->registrar($paquete, $usuario, 'archivo_subido', $detalle.($reemplazados ? ' · reemplaza a '.implode(', ', $reemplazados) : ''));

                return $registro;
            });
        } catch (\Throwable $exception) {
            $this->disco()->delete($ruta);
            throw $exception;
        }
    }

    public function eliminarArchivo(PaqueteTerritorial $paquete, ArchivoTerritorial $archivo, User $usuario): void
    {
        $this->exigirEditable($paquete);
        DB::transaction(function () use ($paquete, $archivo, $usuario) {
            $nombre = $archivo->tipo.': '.$archivo->nombre_original;
            $this->borrarArchivo($archivo);
            if ($paquete->estado === PaqueteTerritorial::VALIDADO) {
                $paquete->update(['estado' => PaqueteTerritorial::BORRADOR]);
            }
            $this->limpiarValidacionesDePaquete($paquete);
            $this->registrar($paquete, $usuario, 'archivo_eliminado', $nombre);
        });
    }

    /** Validación del paquete completo. Sin errores ⇒ queda «validado» y pasa a revisión humana. */
    public function validar(PaqueteTerritorial $paquete, User $usuario): PaqueteTerritorial
    {
        $this->exigirEditable($paquete);

        return DB::transaction(function () use ($paquete, $usuario) {
            $this->limpiarValidacionesDePaquete($paquete);
            foreach ($this->validador->validarPaquete($paquete) as $validacion) {
                ValidacionTerritorial::create([...$validacion, 'paquete_id' => $paquete->id]);
            }
            $errores = $paquete->validaciones()->where('resultado', ValidacionTerritorial::ERROR)->count();
            $paquete->update(['estado' => $errores ? PaqueteTerritorial::BORRADOR : PaqueteTerritorial::VALIDADO]);
            $this->registrar($paquete, $usuario, 'validado', $errores ? "Validación con {$errores} error(es): sigue en borrador." : 'Validación sin errores: listo para revisión y publicación.');

            return $paquete->fresh();
        });
    }

    public function confirmar(PaqueteTerritorial $paquete, ValidacionTerritorial $validacion, User $usuario): void
    {
        if ($validacion->paquete_id !== $paquete->id || ! $validacion->requiere_confirmacion || $validacion->resultado !== ValidacionTerritorial::ADVERTENCIA) {
            throw ValidationException::withMessages(['validacion' => 'Esta validación no requiere confirmación.']);
        }
        $this->exigirEditable($paquete);
        $validacion->update(['confirmada_en' => now(), 'confirmada_por' => $usuario->id]);
        $this->registrar($paquete, $usuario, 'advertencia_confirmada', $validacion->detalle ?? $validacion->regla);
    }

    public function publicar(PaqueteTerritorial $paquete, User $usuario): PaqueteTerritorial
    {
        return DB::transaction(function () use ($paquete, $usuario) {
            $paquete = PaqueteTerritorial::query()->lockForUpdate()->findOrFail($paquete->id);
            if ($paquete->estado !== PaqueteTerritorial::VALIDADO) {
                throw ValidationException::withMessages(['paquete' => 'Solo se puede publicar un paquete validado.']);
            }
            $bloqueos = $paquete->validaciones()->get()->filter->bloquea();
            if ($bloqueos->isNotEmpty()) {
                throw ValidationException::withMessages(['paquete' => 'Hay '.$bloqueos->count().' validación(es) con error o advertencias sin confirmar.']);
            }
            $paquete->update(['estado' => PaqueteTerritorial::PUBLICADO, 'publicado_en' => now(), 'publicado_por' => $usuario->id]);
            $this->registrar($paquete, $usuario, 'publicado', "Versión {$paquete->version} publicada. Desde ahora es inmutable.");

            return $paquete->fresh();
        });
    }

    public function retirar(PaqueteTerritorial $paquete, string $motivo, User $usuario): PaqueteTerritorial
    {
        if ($paquete->estado !== PaqueteTerritorial::PUBLICADO) {
            throw ValidationException::withMessages(['paquete' => 'Solo se puede retirar un paquete publicado.']);
        }
        $paquete->update(['estado' => PaqueteTerritorial::RETIRADO, 'retirado_en' => now(), 'retirado_por' => $usuario->id, 'motivo_retiro' => $motivo]);
        $this->registrar($paquete, $usuario, 'retirado', $motivo);

        return $paquete->fresh();
    }

    private function exigirEditable(PaqueteTerritorial $paquete): void
    {
        if (! $paquete->fresh()->esEditable()) {
            throw ValidationException::withMessages(['paquete' => 'La versión '.$paquete->version.' está '.$paquete->fresh()->estado.' y ya no admite cambios. Cree una versión nueva.']);
        }
    }

    private function borrarArchivo(ArchivoTerritorial $archivo): void
    {
        ValidacionTerritorial::query()->where('archivo_id', $archivo->id)->delete();
        $ruta = $archivo->ruta;
        $archivo->delete();
        $this->disco()->delete($ruta);
        $directorio = dirname($ruta);
        if ($directorio !== '.' && $this->disco()->files($directorio) === []) {
            $this->disco()->deleteDirectory($directorio);
        }
    }

    /** Las validaciones del paquete completo se recalculan con «Validar». */
    private function limpiarValidacionesDePaquete(PaqueteTerritorial $paquete): void
    {
        ValidacionTerritorial::query()->where('paquete_id', $paquete->id)->whereNull('archivo_id')->delete();
    }

    private function nombreSeguro(string $original): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $base = Str::slug(pathinfo($original, PATHINFO_FILENAME), '_') ?: 'archivo';

        return Str::limit($base, 150, '').($extension ? '.'.$extension : '');
    }
}
