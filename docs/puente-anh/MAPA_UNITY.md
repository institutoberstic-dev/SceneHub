# Mapa de dependencias con Unity · Fase 1A

Fecha: 14-oct-2026 · Estado: **Pendiente de confirmación por el equipo de Unity**. Por defecto, todo lo que aparece aquí es **intocable** y cualquier cambio que lo afecte es de **riesgo alto** (protocolo §3.5).

## Fuentes de evidencia y su peso

| Fuente | Qué dice | Peso |
|---|---|---|
| `routes/api.php` (comentario línea 17: «Alias históricos que Unity ya consume») | Unity consume `/solar` y `/resultados-solares` | Documental |
| `docs/unity-data-api.md` | Contrato completo para Unity: manifiesto, simulación, tecnologías e imágenes, con ejemplo C# | Documental |
| `tests/Feature/PublicApiTest.php`, `UnityImageUploadTest.php`, `ScenarioSolarImportTest.php` | Fijan la forma de las respuestas (rutas nuevas y alias) | Contrato probado en SceneHub |
| Copia local del proyecto Unity (`D:\Backups\GuajiraTechPre-Alpha-PC\Assets`), leída **solo para esta consulta**, sin modificar nada | **No se encontró ninguna llamada** a `versiones-datos`, `simulacion`, `solar`, `tecnologias`, `escenarios` ni `unity/imagenes`. Los comunicadores encontrados apuntan a rutas **antiguas ya eliminadas** de SceneHub (`/api/send-simu-sol` en `127.0.0.1:8000`, `Assets Proyecto/…/Api_solarnueva/EnergyLaravelAPICommunicator.cs:8`) o a servicios externos (`…onrender.com/api/send-features`, `…/predict`) | Código, pero **parcial**: el listado se cortó en 2.000 entradas y las URL pueden estar sobrescritas en escenas o prefabs |

**Conclusión:** H6 queda **Pendiente**. Hay dos posibilidades: (a) la integración con la API actual vive en otra copia o rama de Unity, o (b) todavía no se implementó. Cualquiera de las dos cambia la estrategia de privacidad (ajuste 1B-1). **Acción:** pedir al equipo de Unity la lista de URL y campos que usa su versión vigente (P-01).

## Endpoints que Unity consume según la documentación

| Endpoint | Parámetros | Campos que Unity lee (según la documentación) | Autenticación | Qué pasaría si cambia |
|---|---|---|---|---|
| `GET /api/escenarios/{id}/versiones-datos` | `tipo=simulacion\|solar` (obligatorio), `version_actual` (`^\d+\.\d+$`), `sha256_actual` (64 hex) | `estado` (`sin_datos`, `descarga_inicial`, `sin_cambios`, `actualizacion_disponible`, `version_local_posterior`), `mensaje`, `hay_datos`, `version`, `sha256`, `url_datos` | Ninguna | Si cambian los estados, `version` o `url_datos`, Unity no sabe si debe descargar; puede borrar o ignorar su caché |
| `GET /api/escenarios/{id}/simulacion?version=` y alias `/solar` | `version` opcional | `version`, `sha256`, `muestreos."1"/"5"/"10"/"60"[]` con `tiempo_minutos`, `intervalo_minutos` y las 17 columnas de `resultados_simulacion`; `unidades` | Ninguna | Si se renombra una columna, el gemelo deja de mostrar la variable. Si cambia el `sha256`, fuerza descargas |
| `GET /api/tecnologias` | — | `codigo`, `nombre`, `categoria{codigo,nombre}`, `activo` | Ninguna | Unity decide qué objetos 3D habilita por `codigo` (`HABER-BOSH` incluido) |
| `GET /api/escenarios/{id}` | — | `id`, `numero`, `nombre`, `descripcion`, `tecnologias[]` (documentado). **No se sabe** si Unity lee `owner`, `users` o `contenidos` | Ninguna | Quitar correos o rutas **podría** romper Unity si deserializa esos campos con un modelo estricto |
| `GET /api/escenarios` | — | Listado (no está documentado qué campos usa) | Ninguna | idem |
| `POST /api/unity/imagenes` | multipart `imagen` o `imagenes[]` (≤ 10, ≤ 10 MB, png/jpg/jpeg/webp), `escenario_id` opcional | Respuesta 201 `ok`, `imagenes[].url`, `ruta`; 422 `ok=false`, `errores` | Ninguna (throttle 30/min) | Si se mueve el disco o se quita `storage:link`, las `url` devueltas dejan de funcionar |
| `GET /api/emociones[/{meeting}]` | — | `webinar`, `data_promedio`, `data_completa`, `totales` (documentado como «API emocional directa») | Ninguna | Fuera del alcance ANH, pero cualquier recorte de privacidad debe consultarse |
| `GET /api/escenarios/{id}/resultados-simulacion` (alias `/resultados`, `/resultados-solares`) | — | Lo usa la web; el comentario dice que Unity usa el alias `/resultados-solares` | Ninguna | idem |

## Lista de lo intocable (hasta que Unity confirme lo contrario)

1. Rutas, métodos y nombres de `routes/api.php`, **incluidos los alias** `/solar` y `/resultados-solares` y el parámetro `tipo=solar`.
2. La forma JSON de `versiones-datos` y `simulacion`: claves, estados, `version` como cadena «N.M», `sha256` y `url_datos` relativa.
3. Los nombres de las 17 columnas de `resultados_simulacion` tal como salen en `muestreos` y `unidades`.
4. El algoritmo del SHA-256 de `DataVersions::register` (cualquier cambio fuerza descargas en todos los dispositivos).
5. Los códigos de `tecnologias` y `categorias_tecnologia` (estables; se desactivan, no se borran ni renombran).
6. `POST /api/unity/imagenes`: campos, límites, respuesta y el disco `public` con `storage:link`.
7. Que `/api/*` no exija autenticación **en las rutas existentes**. **Actualización del 14-oct (decisión del integrador):** las rutas existentes quedan en dos coberturas de token (`simulacion` y `emociones`). Por defecto están en modo **opcional** y no cambian nada para Unity. Pasar a modo **obligatorio** (`API_TOKEN_SIMULACION=obligatorio` en el `.env`) rompe a cualquier cliente que no envíe el token. Antes de activarlo, Unity debe enviar `Authorization: Bearer …` y el token debe mostrar «Último uso» en `puente:token-listar`.
8. Los `id` de escenario (Unity los guarda en caché por escenario).

## Cómo se protegerá en la fase 1C

- **Fotos de contrato:** antes de cada rama, guardar la respuesta JSON de cada endpoint de esta tabla contra la BD local (`php artisan serve` + `curl`) y compararla después del cambio (diff de claves y tipos).
- Pruebas PHPUnit nuevas que fijen las claves que Unity lee.
- Nada de lo anterior se modifica sin la respuesta a P-01.
