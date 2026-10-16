<?php

namespace Tests\Feature;

use App\Models\PaqueteTerritorial;
use App\Models\User;
use App\Models\ValidacionTerritorial;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PDO;
use Tests\TestCase;

class TerritorioModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        Storage::fake('local');
        Storage::fake('public');
    }

    private function usuario(array $permisos): User
    {
        $usuario = User::factory()->create();
        $usuario->assignRole('usuario');
        $usuario->givePermissionTo($permisos);

        return $usuario;
    }

    /** GeoPackage mínimo válido (tablas OGC + capa con hex_id) creado con PDO SQLite. */
    public static function gpkg(int $filas = 648, int $srid = 9377, string $capa = 'malla_hex_1ha_gee', bool $repetir = false, string $campo = 'hex_id', array $geometrias = ['poligono']): UploadedFile
    {
        $ruta = tempnam(sys_get_temp_dir(), 'gpkg').'.gpkg';
        $pdo = new PDO('sqlite:'.$ruta);
        $pdo->exec('CREATE TABLE gpkg_spatial_ref_sys (srs_name TEXT, srs_id INTEGER PRIMARY KEY, organization TEXT, organization_coordsys_id INTEGER, definition TEXT, description TEXT)');
        $pdo->exec('CREATE TABLE gpkg_contents (table_name TEXT PRIMARY KEY, data_type TEXT, identifier TEXT, srs_id INTEGER)');
        $pdo->exec('CREATE TABLE gpkg_geometry_columns (table_name TEXT, column_name TEXT, geometry_type_name TEXT, srs_id INTEGER, z TINYINT, m TINYINT)');
        $pdo->exec("CREATE TABLE \"{$capa}\" (fid INTEGER PRIMARY KEY, geom BLOB, \"{$campo}\" INTEGER, indicador REAL)");
        $pdo->prepare("INSERT INTO gpkg_contents VALUES (?, 'features', ?, ?)")->execute([$capa, $capa, $srid]);
        $declarado = count(array_unique($geometrias)) === 1 ? ['punto' => 'POINT', 'linea' => 'LINESTRING', 'poligono' => 'POLYGON', null => 'GEOMETRY'][$geometrias[0]] : 'GEOMETRY';
        $pdo->prepare("INSERT INTO gpkg_geometry_columns VALUES (?, 'geom', ?, ?, 0, 0)")->execute([$capa, $declarado, $srid]);
        $insert = $pdo->prepare("INSERT INTO \"{$capa}\" (geom, \"{$campo}\", indicador) VALUES (?, ?, ?)");
        for ($i = 1; $i <= $filas; $i++) {
            $insert->bindValue(1, self::geometriaGpkg($geometrias[($i - 1) % count($geometrias)], $srid, $i), PDO::PARAM_LOB);
            $insert->bindValue(2, $repetir && $i === 2 ? 1 : $i);
            $insert->bindValue(3, $i / 10);
            $insert->execute();
        }
        $pdo = null;

        return new UploadedFile($ruta, 'energia_gee_malla_variables_v01_20260923.gpkg', 'application/geopackage+sqlite3', null, true);
    }

    /** Geometría GeoPackage (encabezado GP sin sobre + WKB little endian) cerca de La Paz en EPSG:9377; null = sin geometría. */
    public static function geometriaGpkg(?string $familia, int $srid, int $i): ?string
    {
        if ($familia === null) {
            return null;
        }
        [$x, $y] = [5064000 + ($i % 20) * 100, 2860000 + intdiv($i, 20) * 100];
        $puntos = fn (array $p) => pack('V', count($p)).implode('', array_map(fn ($c) => pack('ee', ...$c), $p));
        $wkb = match ($familia) {
            'punto' => pack('CV', 1, 1).pack('ee', $x, $y),
            'linea' => pack('CV', 1, 2).$puntos([[$x, $y], [$x + 50, $y + 50]]),
            'poligono' => pack('CVV', 1, 3, 1).$puntos([[$x, $y], [$x + 50, $y], [$x + 50, $y + 50], [$x, $y]]),
        };

        return 'GP'.pack('CCV', 0, 1, $srid).$wkb;
    }

    public static function glb(bool $valido = true): UploadedFile
    {
        $json = '{"asset":{"version":"2.0"}}';
        $json .= str_repeat(' ', (4 - strlen($json) % 4) % 4);
        $contenido = pack('V', strlen($json)).'JSON'.$json;
        $binario = $valido ? 'glTF'.pack('VV', 2, 12 + strlen($contenido)).$contenido : 'no es un glb';

        return UploadedFile::fake()->createWithContent('la_paz_escena.glb', $binario);
    }

    public static function archivoJson(string $nombre, array|string $datos): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($nombre, is_string($datos) ? $datos : json_encode($datos));
    }

    private function crearPaquete(User $usuario, string $version = 'v01'): int
    {
        return $this->actingAs($usuario)->postJson('/territorio-paquetes', ['version' => $version, 'notas' => 'Paquete de prueba'])
            ->assertCreated()->json('data.id');
    }

    private function subir(int $paquete, string $tipo, UploadedFile $archivo, array $extra = [])
    {
        return $this->postJson("/territorio-paquetes/{$paquete}/archivos", ['tipo' => $tipo, 'archivo' => $archivo, ...$extra]);
    }

    private function paqueteCompleto(User $usuario, int $celdas = 648): int
    {
        $id = $this->crearPaquete($usuario);
        $this->subir($id, 'malla_gpkg', self::gpkg($celdas))->assertCreated();
        $this->subir($id, 'sitio_json', self::archivoJson('la_paz_sitio.json', ['valores' => [['clave' => 'precipitacion', 'valor' => 1.2]]]))->assertCreated();
        $this->subir($id, 'escena_glb', self::glb())->assertCreated();
        $this->subir($id, 'georef_json', self::archivoJson('la_paz_escena_georef.json', ['crs' => 'EPSG:9377']))->assertCreated();

        return $id;
    }

    public function test_el_modulo_exige_permisos_propios(): void
    {
        $sinPermiso = $this->usuario(['escenarios.leer']);
        $this->actingAs($sinPermiso)->getJson('/territorio-data')->assertForbidden();
        $this->actingAs($sinPermiso)->postJson('/territorio-paquetes', ['version' => 'v01'])->assertForbidden();

        $lector = $this->usuario(['territorio.ver']);
        $this->actingAs($lector)->getJson('/territorio-data')->assertOk()->assertJsonPath('puede_crear', false);
        $this->actingAs($lector)->postJson('/territorio-paquetes', ['version' => 'v01'])->assertForbidden();
    }

    public function test_flujo_completo_borrador_validacion_publicacion_e_inmutabilidad(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $publicador = $this->usuario(['territorio.ver', 'territorio.publicar']);
        $id = $this->paqueteCompleto($subidor);

        $detalle = $this->actingAs($subidor)->getJson("/territorio-data/{$id}")->assertOk();
        $detalle->assertJsonPath('estado', 'borrador')->assertJsonCount(4, 'archivos')->assertJsonMissingPath('archivos.0.ruta');
        $this->assertSame(0, ValidacionTerritorial::where('resultado', 'error')->count());

        // El que sube no puede publicar.
        $this->actingAs($subidor)->postJson("/territorio-paquetes/{$id}/validar")->assertOk()->assertJsonPath('data.estado', 'validado');
        $this->actingAs($subidor)->postJson("/territorio-paquetes/{$id}/publicar")->assertForbidden();

        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/publicar")->assertOk()->assertJsonPath('data.estado', 'publicado');

        // Publicado = inmutable: no se suben ni se borran archivos.
        $archivo = PaqueteTerritorial::find($id)->archivos()->first();
        $this->actingAs($subidor)->postJson("/territorio-paquetes/{$id}/archivos", ['tipo' => 'sitio_json', 'archivo' => self::archivoJson('otro.json', ['valores' => []])])->assertForbidden();
        $this->actingAs($subidor)->deleteJson("/territorio-paquetes/{$id}/archivos/{$archivo->id}")->assertForbidden();
        $this->assertSame(4, PaqueteTerritorial::find($id)->archivos()->count());

        // Se retira (con motivo), no se borra.
        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/retirar", ['motivo' => 'corto'])->assertUnprocessable();
        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/retirar", ['motivo' => 'Se reemplaza por una versión corregida.'])
            ->assertOk()->assertJsonPath('data.estado', 'retirado');
        $this->assertDatabaseHas('paquete_territorial', ['id' => $id, 'estado' => 'retirado']);
        $this->assertGreaterThanOrEqual(7, PaqueteTerritorial::find($id)->registros()->count());
    }

    public function test_archivos_en_disco_privado_y_descarga_con_permiso(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $id = $this->crearPaquete($subidor);
        $this->subir($id, 'georef_json', self::archivoJson('la_paz_escena_georef.json', ['crs' => 'EPSG:9377']))->assertCreated();
        $archivo = PaqueteTerritorial::find($id)->archivos()->first();

        Storage::disk('local')->assertExists($archivo->ruta);
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(hash('sha256', json_encode(['crs' => 'EPSG:9377'])), $archivo->sha256);

        $this->actingAs($subidor)->get("/territorio-paquetes/{$id}/archivos/{$archivo->id}/descargar")->assertOk();
        $this->actingAs($this->usuario(['escenarios.leer']))->get("/territorio-paquetes/{$id}/archivos/{$archivo->id}/descargar")->assertForbidden();
    }

    public function test_validaciones_ligeras_de_cada_tipo(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $id = $this->crearPaquete($subidor);
        // Resultado de cada regla del ÚLTIMO archivo subido.
        $reglas = fn () => ValidacionTerritorial::query()->where('paquete_id', $id)->where('archivo_id', PaqueteTerritorial::find($id)->archivos()->max('id'))->get()->mapWithKeys(fn ($v) => [$v->regla => $v->resultado]);

        $this->actingAs($subidor);
        $this->subir($id, 'malla_gpkg', self::gpkg(648, 4326))->assertCreated();
        $this->assertSame('error', $reglas()['gpkg.srs']);

        $this->subir($id, 'malla_gpkg', self::gpkg(10, 9377, repetir: true))->assertCreated();
        $this->assertSame('error', $reglas()['gpkg.hex_id']);
        $this->assertSame('advertencia', $reglas()['gpkg.conteo_celdas']);

        $this->subir($id, 'malla_gpkg', self::gpkg(648, 9377, 'otra_capa'))->assertCreated();
        $this->assertSame('error', $reglas()['gpkg.capa']);

        $this->subir($id, 'sitio_json', self::archivoJson('sitio.json', '{no es json'))->assertCreated();
        $this->assertSame('error', $reglas()['json.valido']);
        $this->subir($id, 'sitio_json', self::archivoJson('sitio.json', ['otra' => 1]))->assertCreated();
        $this->assertSame('error', $reglas()['json.claves']);

        $this->subir($id, 'escena_glb', self::glb(false))->assertCreated();
        $this->assertSame('error', $reglas()['glb.firma']);

        $this->subir($id, 'georef_json', self::archivoJson('georef.json', ['a' => 1]), ['sha256_declarado' => str_repeat('a', 64)])->assertCreated();
        $this->assertSame('error', $reglas()['archivo.sha256']);

        // Extensión equivocada y «otro» sin descripción se rechazan antes de guardar.
        $this->subir($id, 'escena_glb', self::archivoJson('escena.json', ['x' => 1]))->assertUnprocessable();
        $this->subir($id, 'otro', self::archivoJson('nota.json', ['x' => 1]))->assertUnprocessable()->assertJsonValidationErrors('descripcion');

        // Los tipos únicos se reemplazan: solo queda una malla.
        $this->assertSame(1, PaqueteTerritorial::find($id)->archivos()->where('tipo', 'malla_gpkg')->count());

        // Con errores, «Validar» lo deja en borrador.
        $this->postJson("/territorio-paquetes/{$id}/validar")->assertOk()->assertJsonPath('data.estado', 'borrador');
    }

    public function test_tipo_de_geometria_y_sistema_de_referencia_de_las_capas(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $id = $this->crearPaquete($subidor);
        $reglas = fn () => ValidacionTerritorial::query()->where('paquete_id', $id)->where('archivo_id', PaqueteTerritorial::find($id)->archivos()->max('id'))->get()->mapWithKeys(fn ($v) => [$v->regla => $v->resultado]);
        $this->actingAs($subidor);

        // Malla correcta: polígonos, todos con geometría.
        $this->subir($id, 'malla_gpkg', self::gpkg())->assertCreated();
        $this->assertSame('ok', $reglas()['geometria.tipo']);
        $this->assertArrayNotHasKey('geometria.nulas', $reglas()->all());

        // Celdas sin geometría y malla de puntos: error.
        $this->subir($id, 'malla_gpkg', self::gpkg(geometrias: ['poligono', null]))->assertCreated();
        $this->assertSame('error', $reglas()['geometria.nulas']);
        $this->subir($id, 'malla_gpkg', self::gpkg(geometrias: ['punto']))->assertCreated();
        $this->assertSame('error', $reglas()['geometria.tipo']);

        // Una capa que mezcla puntos y polígonos: error (IN-38 5.1.4).
        $this->subir($id, 'cercas', self::gpkg(6, 9377, 'cercas', geometrias: ['linea', 'punto']))->assertCreated();
        $this->assertSame('error', $reglas()['geometria.tipo']);

        // Un solo tipo, pero distinto del esperado: advertencia que exige confirmación.
        $this->subir($id, 'cercas', self::gpkg(6, 9377, 'cercas', geometrias: ['poligono']))->assertCreated();
        $this->assertSame('advertencia', $reglas()['geometria.tipo']);
        $this->subir($id, 'cercas', self::gpkg(6, 9377, 'cercas', geometrias: ['linea']))->assertCreated();
        $this->assertSame('ok', $reglas()['geometria.tipo']);

        // GeoJSON: sin «crs» se interpreta en WGS84 → error; con EPSG:9377 y polígonos → correcto.
        $poligono = ['type' => 'Feature', 'properties' => ['id' => 1], 'geometry' => ['type' => 'Polygon', 'coordinates' => [[[5064000, 2860000], [5064050, 2860000], [5064050, 2860050], [5064000, 2860000]]]]];
        $this->subir($id, 'superficies', self::archivoJson('superficies.geojson', ['type' => 'FeatureCollection', 'features' => [$poligono]]))->assertCreated();
        $this->assertSame('error', $reglas()['geojson.crs']);
        $crs = ['type' => 'name', 'properties' => ['name' => 'urn:ogc:def:crs:EPSG::9377']];
        $this->subir($id, 'superficies', self::archivoJson('superficies.geojson', ['type' => 'FeatureCollection', 'crs' => $crs, 'features' => [$poligono]]))->assertCreated();
        $this->assertSame('ok', $reglas()['geojson.crs']);
        $this->assertSame('ok', $reglas()['geometria.tipo']);
        $punto = ['type' => 'Feature', 'properties' => [], 'geometry' => ['type' => 'Point', 'coordinates' => [5064000, 2860000]]];
        $this->subir($id, 'superficies', self::archivoJson('superficies.geojson', ['type' => 'FeatureCollection', 'crs' => $crs, 'features' => [$poligono, $punto, ['type' => 'Feature', 'geometry' => null]]]))->assertCreated();
        $this->assertSame('error', $reglas()['geometria.tipo']);
        $this->assertSame('error', $reglas()['geometria.nulas']);
    }

    public function test_lee_geometrias_con_sobre_big_endian_y_codigos_iso(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $id = $this->crearPaquete($subidor);
        $reglas = fn () => ValidacionTerritorial::query()->where('paquete_id', $id)->where('archivo_id', PaqueteTerritorial::find($id)->archivos()->max('id'))->get()->mapWithKeys(fn ($v) => [$v->regla => $v->resultado]);
        $this->actingAs($subidor);
        // Encabezado GP: banderas = (código de sobre << 1) | orden de bytes (1 = little endian).
        $blob = fn (int $sobre, bool $little, int $tipo) => 'GP'.pack('CC', 0, ($sobre << 1) | ($little ? 1 : 0)).pack($little ? 'V' : 'N', 9377)
            .str_repeat("\0", [0 => 0, 1 => 32, 2 => 48, 4 => 64][$sobre])
            .pack('C', $little ? 1 : 0).pack($little ? 'V' : 'N', $tipo).str_repeat("\0", 16);
        $con = function (array $blobs) {
            $archivo = self::gpkg(count($blobs), 9377, 'superficies');
            $pdo = new PDO('sqlite:'.$archivo->getPathname());
            foreach ($blobs as $i => $contenido) {
                $consulta = $pdo->prepare('UPDATE "superficies" SET geom = ? WHERE fid = ?');
                $consulta->bindValue(1, $contenido, PDO::PARAM_LOB);
                $consulta->bindValue(2, $i + 1);
                $consulta->execute();
            }
            $pdo = null;

            return $archivo;
        };

        // Polígono Z ISO (1003) big endian con sobre de 32 bytes, multipolígono con sobre de 48 y polígono con sobre de 64.
        $this->subir($id, 'superficies', $con([$blob(1, false, 1003), $blob(2, true, 6), $blob(4, true, 3)]))->assertCreated();
        $this->assertSame('ok', $reglas()['geometria.tipo']);
        $this->assertArrayNotHasKey('geometria.nulas', $reglas()->all());

        // Un punto M (2001) big endian entre polígonos: mezcla de tipos.
        $this->subir($id, 'superficies', $con([$blob(0, false, 2001), $blob(1, true, 3)]))->assertCreated();
        $this->assertSame('error', $reglas()['geometria.tipo']);
    }

    public function test_conteo_inesperado_exige_confirmacion_antes_de_publicar(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $publicador = $this->usuario(['territorio.ver', 'territorio.publicar']);
        $id = $this->paqueteCompleto($subidor, 640);
        $this->actingAs($subidor)->postJson("/territorio-paquetes/{$id}/validar")->assertOk()->assertJsonPath('data.estado', 'validado');

        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/publicar")->assertUnprocessable();
        $aviso = ValidacionTerritorial::where('paquete_id', $id)->where('regla', 'gpkg.conteo_celdas')->firstOrFail();
        $this->assertTrue($aviso->requiere_confirmacion);
        $this->actingAs($subidor)->postJson("/territorio-paquetes/{$id}/validaciones/{$aviso->id}/confirmar")->assertForbidden();
        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/validaciones/{$aviso->id}/confirmar")->assertOk();
        $this->actingAs($publicador)->postJson("/territorio-paquetes/{$id}/publicar")->assertOk()->assertJsonPath('data.estado', 'publicado');
    }

    public function test_faltan_obligatorios_y_version_unica(): void
    {
        $subidor = $this->usuario(['territorio.ver', 'territorio.subir']);
        $id = $this->crearPaquete($subidor);
        $this->actingAs($subidor)->postJson('/territorio-paquetes', ['version' => 'v01'])->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->actingAs($subidor)->postJson('/territorio-paquetes', ['version' => '1'])->assertUnprocessable();
        $this->actingAs($subidor)->getJson('/territorio-data')->assertJsonPath('siguiente_version', 'v02');

        $this->subir($id, 'malla_gpkg', self::gpkg())->assertCreated();
        $this->postJson("/territorio-paquetes/{$id}/validar")->assertOk()->assertJsonPath('data.estado', 'borrador');
        $this->assertDatabaseHas('validacion_territorial', ['paquete_id' => $id, 'regla' => 'paquete.obligatorios', 'resultado' => 'error']);
    }
}
