<?php

namespace App\Services\Territorio;

use App\Models\ArchivoTerritorial;
use App\Models\PaqueteTerritorial;
use App\Models\ValidacionTerritorial;
use PDO;
use Throwable;

/**
 * Validaciones LIGERAS del paquete territorial. Solo inspecciona: no reproyecta,
 * no recalcula y no modifica geometrías.
 *
 * Cada validación es ['regla', 'resultado' (ok|advertencia|error), 'detalle', 'requiere_confirmacion'].
 */
class ValidadorTerritorial
{
    private const OK = ValidacionTerritorial::OK;

    private const ADVERTENCIA = ValidacionTerritorial::ADVERTENCIA;

    private const ERROR = ValidacionTerritorial::ERROR;

    /** @return array<int, array{regla: string, resultado: string, detalle: string, requiere_confirmacion: bool, capa?: string}> */
    public function validarArchivo(ArchivoTerritorial $archivo, string $ruta): array
    {
        $tipo = config("territorio.tipos.{$archivo->tipo}");
        $resultados = [];
        if (! $tipo) {
            return [$this->r('archivo.tipo', self::ERROR, "Tipo de archivo desconocido: «{$archivo->tipo}».")];
        }

        $extension = strtolower(pathinfo($archivo->nombre_original, PATHINFO_EXTENSION));
        if (! in_array($extension, $tipo['extensiones'], true)) {
            $resultados[] = $this->r('archivo.extension', self::ERROR, 'Extensión «.'.$extension.'» no admitida para '.$tipo['nombre'].' (se admite: .'.implode(', .', $tipo['extensiones']).').');
        }
        if ($archivo->tamano > $tipo['max_mb'] * 1024 * 1024) {
            $resultados[] = $this->r('archivo.tamano', self::ERROR, "El archivo supera el máximo de {$tipo['max_mb']} MB.");
        }
        if ($archivo->sha256_declarado && ! hash_equals(strtolower($archivo->sha256_declarado), $archivo->sha256)) {
            $resultados[] = $this->r('archivo.sha256', self::ERROR, 'La huella SHA-256 declarada no coincide con la calculada por el servidor: el archivo cambió o llegó incompleto.');
        } else {
            $resultados[] = $this->r('archivo.sha256', self::OK, 'Huella SHA-256 calculada en el servidor'.($archivo->sha256_declarado ? ' y coincide con la declarada.' : '.'));
        }

        $firma = $this->firma($ruta);
        $esperada = $tipo['firma'];
        if ($esperada === 'sqlite' || ($esperada === 'geo' && $extension === 'gpkg')) {
            if ($firma !== 'sqlite') {
                return [...$resultados, $this->r('archivo.firma', self::ERROR, 'El contenido no es un GeoPackage (no es una base SQLite).')];
            }
            $resultados[] = $this->r('archivo.firma', self::OK, 'Firma de GeoPackage (SQLite) válida.');

            return [...$resultados, ...$this->gpkg($archivo, $ruta)];
        }
        if ($esperada === 'json' || ($esperada === 'geo' && in_array($extension, ['json', 'geojson'], true))) {
            return [...$resultados, ...$this->json($archivo, $ruta, $esperada === 'geo')];
        }
        if ($esperada === 'glb') {
            return [...$resultados, $this->glb($ruta)];
        }
        if ($esperada === 'imagen') {
            $resultados[] = in_array($firma, ['tif', 'jpg', 'png'], true)
                ? $this->r('archivo.firma', self::OK, 'Firma de imagen válida ('.strtoupper($firma).').')
                : $this->r('archivo.firma', self::ERROR, 'El contenido no es una imagen TIFF, JPEG o PNG.');
            if (! $archivo->licencia) {
                $resultados[] = $this->r('ortofoto.licencia', self::ADVERTENCIA, 'La ortofoto no declara licencia. Solo puede incluirse si la licencia lo permite: confirme antes de publicar.', true);
            }

            return $resultados;
        }

        return [...$resultados, $this->r('archivo.firma', self::OK, 'Archivo complementario registrado (sin validación de contenido).')];
    }

    /** Validaciones del paquete completo (archivos obligatorios). */
    public function validarPaquete(PaqueteTerritorial $paquete): array
    {
        $presentes = $paquete->archivos()->pluck('tipo')->unique()->all();
        $faltan = collect(config('territorio.tipos'))
            ->filter(fn (array $tipo, string $clave) => $tipo['obligatorio'] && ! in_array($clave, $presentes, true))
            ->map(fn (array $tipo) => $tipo['nombre'])
            ->values()
            ->all();

        return [$faltan
            ? $this->r('paquete.obligatorios', self::ERROR, 'Faltan archivos obligatorios: '.implode('; ', $faltan).'.')
            : $this->r('paquete.obligatorios', self::OK, 'Están todos los archivos obligatorios.')];
    }

    private function r(string $regla, string $resultado, string $detalle, bool $confirmar = false, array $extra = []): array
    {
        return ['regla' => $regla, 'resultado' => $resultado, 'detalle' => $detalle, 'requiere_confirmacion' => $confirmar, ...$extra];
    }

    private function firma(string $ruta): string
    {
        $cabecera = (string) @file_get_contents($ruta, false, null, 0, 16);

        return match (true) {
            str_starts_with($cabecera, "SQLite format 3\0") => 'sqlite',
            str_starts_with($cabecera, 'glTF') => 'glb',
            str_starts_with($cabecera, "II*\0"), str_starts_with($cabecera, "MM\0*") => 'tif',
            str_starts_with($cabecera, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($cabecera, "\x89PNG") => 'png',
            default => 'otro',
        };
    }

    private function glb(string $ruta): array
    {
        $cabecera = (string) @file_get_contents($ruta, false, null, 0, 12);
        if (strlen($cabecera) < 12 || substr($cabecera, 0, 4) !== 'glTF') {
            return $this->r('glb.firma', self::ERROR, 'El archivo no tiene la firma glTF binaria (GLB).');
        }
        $datos = unpack('Vversion/Vlongitud', substr($cabecera, 4, 8));
        if ($datos['version'] !== 2) {
            return $this->r('glb.firma', self::ERROR, "La escena es glTF versión {$datos['version']}; se espera la versión 2.");
        }
        if ($datos['longitud'] !== filesize($ruta)) {
            return $this->r('glb.firma', self::ERROR, 'La longitud declarada en el GLB no coincide con el tamaño del archivo: puede estar incompleto.');
        }

        return $this->r('glb.firma', self::OK, 'Firma glTF 2.0 (GLB) válida.');
    }

    private function json(ArchivoTerritorial $archivo, string $ruta, bool $geo): array
    {
        try {
            $datos = json_decode((string) file_get_contents($ruta), true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return [$this->r('json.valido', self::ERROR, 'El archivo no es un JSON válido.')];
        }
        $resultados = [$this->r('json.valido', self::OK, 'JSON válido.')];
        if ($geo) {
            if (! is_array($datos) || ($datos['type'] ?? null) !== 'FeatureCollection' || ! is_array($datos['features'] ?? null)) {
                return [...$resultados, $this->r('geojson.estructura', self::ERROR, 'Se esperaba un GeoJSON de tipo FeatureCollection.')];
            }
            $resultados[] = $this->r('geojson.estructura', self::OK, 'GeoJSON con '.count($datos['features']).' elementos.');
            if ($archivo->tipo === 'huellas') {
                $resultados[] = $this->conteo('huellas.conteo', 'Edificaciones', count($datos['features']), (int) config('territorio.conteos.edificaciones'));
            }

            return $resultados;
        }
        $claves = config("territorio.claves_json.{$archivo->tipo}", []);
        $faltan = is_array($datos) ? array_values(array_diff($claves, array_keys($datos))) : $claves;
        $resultados[] = $faltan
            ? $this->r('json.claves', self::ERROR, 'Faltan claves obligatorias: '.implode(', ', $faltan).'.')
            : $this->r('json.claves', self::OK, $claves ? 'Tiene las claves mínimas ('.implode(', ', $claves).').' : 'Sin claves mínimas configuradas.');

        return $resultados;
    }

    private function conteo(string $regla, string $nombre, int $real, int $esperado): array
    {
        return $real === $esperado
            ? $this->r($regla, self::OK, "{$nombre}: {$real} (esperado).")
            : $this->r($regla, self::ADVERTENCIA, "{$nombre}: se encontraron {$real} y se esperaban {$esperado}. Confirme que es correcto antes de publicar.", true);
    }

    private function gpkg(ArchivoTerritorial $archivo, string $ruta): array
    {
        if (! extension_loaded('pdo_sqlite')) {
            return [$this->r('gpkg.servidor', self::ADVERTENCIA, 'El servidor no tiene la extensión pdo_sqlite: no se pudo revisar el interior del GeoPackage. La revisión completa la hará el Puente de datos.', true)];
        }
        $esMalla = $archivo->tipo === 'malla_gpkg';
        try {
            $pdo = new PDO('sqlite:'.$ruta, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::SQLITE_ATTR_OPEN_FLAGS => PDO::SQLITE_OPEN_READONLY]);
            $tablas = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
            if (! in_array('gpkg_contents', $tablas, true) || ! in_array('gpkg_geometry_columns', $tablas, true)) {
                return [$this->r('gpkg.contents', self::ERROR, 'No es un GeoPackage válido: faltan las tablas gpkg_contents o gpkg_geometry_columns.')];
            }
            $capas = $pdo->query("SELECT table_name FROM gpkg_contents WHERE data_type = 'features' ORDER BY table_name")->fetchAll(PDO::FETCH_COLUMN);
            $capa = $archivo->capa ?: ($esMalla ? config('territorio.capa_malla') : ($capas[0] ?? null));
            if (! $capa || ! in_array($capa, $capas, true)) {
                return [$this->r('gpkg.capa', self::ERROR, 'No existe la capa «'.($capa ?? '—').'». Capas del archivo: '.($capas ? implode(', ', $capas) : 'ninguna').'.')];
            }
            $resultados = [$this->r('gpkg.capa', self::OK, "Capa «{$capa}» encontrada.", false, ['capa' => $capa])];

            $consulta = $pdo->prepare('SELECT srs_id FROM gpkg_geometry_columns WHERE table_name = ?');
            $consulta->execute([$capa]);
            $srid = (int) $consulta->fetchColumn();
            $esperado = (int) config('territorio.srid');
            $resultados[] = $srid === $esperado
                ? $this->r('gpkg.srs', self::OK, "Sistema de referencia EPSG:{$srid}.")
                : $this->r('gpkg.srs', self::ERROR, "La capa está en SRID {$srid} y debe estar en EPSG:{$esperado}. SceneHub no reproyecta: corríjala en el origen.");

            $tabla = '"'.str_replace('"', '""', $capa).'"';
            $total = (int) $pdo->query("SELECT COUNT(*) FROM {$tabla}")->fetchColumn();
            if ($esMalla) {
                $campo = config('territorio.campo_hex');
                $columnas = array_column($pdo->query("PRAGMA table_info({$tabla})")->fetchAll(PDO::FETCH_ASSOC), 'name');
                if (! in_array($campo, $columnas, true)) {
                    $resultados[] = $this->r('gpkg.hex_id', self::ERROR, "La malla no tiene el campo «{$campo}».");
                } else {
                    $columna = '"'.str_replace('"', '""', $campo).'"';
                    $nulos = (int) $pdo->query("SELECT COUNT(*) FROM {$tabla} WHERE {$columna} IS NULL OR {$columna} = ''")->fetchColumn();
                    $distintos = (int) $pdo->query("SELECT COUNT(DISTINCT {$columna}) FROM {$tabla} WHERE {$columna} IS NOT NULL AND {$columna} <> ''")->fetchColumn();
                    $repetidos = ($total - $nulos) - $distintos;
                    $resultados[] = ($nulos || $repetidos)
                        ? $this->r('gpkg.hex_id', self::ERROR, "{$campo}: {$nulos} vacíos y {$repetidos} repetidos. Corríjalo en el origen.")
                        : $this->r('gpkg.hex_id', self::OK, "{$campo} sin vacíos ni repetidos.");
                }
                $resultados[] = $this->conteo('gpkg.conteo_celdas', 'Celdas de la malla', $total, (int) config('territorio.conteos.celdas'));
            } elseif ($archivo->tipo === 'huellas') {
                $resultados[] = $this->conteo('huellas.conteo', 'Edificaciones', $total, (int) config('territorio.conteos.edificaciones'));
            } else {
                $resultados[] = $this->r('gpkg.elementos', self::OK, "{$total} elementos en la capa.");
            }

            return $resultados;
        } catch (Throwable $exception) {
            report($exception);

            return [$this->r('gpkg.lectura', self::ERROR, 'No se pudo leer el GeoPackage: el archivo está dañado o no es válido.')];
        } finally {
            $pdo = null;
        }
    }
}
