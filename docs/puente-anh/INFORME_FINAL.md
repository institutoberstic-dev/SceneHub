# Informe · Fase E (parcial)

> **Actualización 14-oct (tarde) · fase 1C, ajustes 6 y 7.** El integrador autorizó el sistema territorial y la API con token. Se implementaron en la rama `feature/territorio-api-token`, de forma aditiva y sin tocar lo que usa Unity. Detalle, pruebas y reversa en `PLAN_AJUSTES.md`; contrato en `CONTRATO_API.md`; uso en `GUIA_USUARIO_TERRITORIO.md`.
> - **Línea base de PHPUnit, ahora sí verificada** con las dependencias exactas de `composer.lock`: 67 pasan, 10 fallan (emociones, preexistentes) y 2 se omiten.
> - **Después:** 82 pasan, con las mismas 10 fallas. Pint sin observaciones en los archivos nuevos.
> - **Pendiente en el PC:** `npm run build`, la prueba visual con capturas y, antes de desplegar, `php -m` en Hostinger (pdo_sqlite) y los límites de subida.
>
> El resto de este informe corresponde al cierre de la etapa 1A.

# Informe de la etapa 1A (detenido en la COMPUERTA 1A)

Fecha: 14-oct-2026 · Encargo v9 · Proyecto GuajiraTech → base de datos ANH

## 1. Resumen ejecutivo
- **SceneHub:** auditoría 1A completa y de solo lectura. **No se modificó ningún archivo de código, configuración ni datos.** Solo se agregaron 4 documentos en `docs/puente-anh/`.
- **Veredicto: «Viable con estos ajustes»**: privacidad de la API, archivos privados, inmutabilidad, catálogo de variables, plantilla del Excel, módulo territorial y API con token. Todos se pueden hacer de forma aditiva.
- **Bloqueante principal:** no se sabe qué consume realmente Unity (P-01). La copia local de Unity no llama a la API actual.
- **App «Puente de datos»:** versión 1.0 construida en un **repositorio separado**, `D:\Backups\PuentePython` (decisión del integrador, P-12), lista para GitHub. Funciona completa con el destino Archivos y con datos de prueba sintéticos. SQL Server queda preparado y desactivado.
- **Pruebas del motor:** 25 de 25 pasan en el entorno de desarrollo, con un sustituto mínimo de pytest. **Falta correrlas con pytest real en el PC** y abrir la interfaz en Windows (sección 4).
- **Siguiente paso:** el integrador revisa `AUDITORIA.md`, `MAPA_UNITY.md` y `PREGUNTAS_PENDIENTES.md`, marca las opciones y autoriza la fase 1B.

## 2. Resumen de la auditoría
- **Stack real:** Laravel 12 (PHP ^8.2) + SPA React 19 / React Router 7 / Vite 7 / Tailwind 4, sin Inertia ni SSR. No hay JavaScript en el servidor. MariaDB en producción; Spatie para permisos.
- **Hipótesis:**
  - Verificadas: H1, H2 (ampliada), H4, H5, H8, H9 y H11.
  - Parcial: H3 (el SHA-256 es de filas normalizadas).
  - Pendientes: H6 (Unity), H7 (línea base) y H10 en producción.
- **Unity:** ver `MAPA_UNITY.md`. Todo lo listado ahí es intocable mientras no haya confirmación.
- **Despliegue:** manual, por `git pull` según `docs/despliegue-hostinger.md` (que apunta a `master`, mientras el trabajo está en `main`: P-09). La app no vive en este repositorio, así que nunca llega a Hostinger.

## 3. Lo implementado
**SceneHub:** nada de código. Documentos nuevos: `docs/puente-anh/AUDITORIA.md`, `MAPA_UNITY.md`, `PREGUNTAS_PENDIENTES.md` y este informe. Sin ramas, migraciones ni commits. Se deshace borrando la carpeta `docs/puente-anh/`.

**App (`D:\Backups\PuentePython`)**, sin git inicializado (los comandos están en su README):
- `motor/`: cliente HTTPS de solo lectura (3 reintentos), lector del Excel con formato largo, territorio (GPKG con `sqlite3`, WKT con SRID 9377, conteos y recuadro), lista blanca, catálogo, estado por destino, historial JSONL, contrato de datos único, destino Archivos (atómico) y destino SQL Server (pyodbc, solo staging, desactivado).
- `ui/`: PySide6 con Inicio, Detalle de revisión, Confirmar carga, Historial (exporta a Excel), Configuración (token en el Administrador de credenciales de Windows, Probar conexión, Carga inicial) y Novedades, con los colores del diseño aprobado.
- `tests/`: las 13 pruebas obligatorias y 12 complementarias, todas sin red.
- `docs/`:
  - `contrato_datos.md` y `sqlserver_staging_borrador.sql`, generados desde el código;
  - `contrato_api_provisional.md`, `prueba_manual_ui.md` y `dependencias_licencias.md`.
- `scripts/construir_exe.ps1` (PyInstaller en modo carpeta) y el flujo de GitHub Actions en `scripts/github/pruebas.yml`. Va en esa carpeta porque `.github/` está protegida para escritura remota; se mueve antes del primer commit.

## 4. Informe honesto de pruebas

| Prueba | Resultado | Estado |
|---|---|---|
| Git de SceneHub: 165 archivos versionados comparados con el índice | Sin cambios; solo archivos ignorados sin seguimiento | Verificado |
| `php -l` sobre 90 archivos PHP de SceneHub | 0 errores, con **PHP 8.3.6, no 8.2** | Verificado (con esa salvedad) |
| `node --test tests/*.test.mjs` (SceneHub) | 13 pasan, 0 fallan (Node 22) | Verificado |
| PHPUnit, `npm run build`, Pint, `composer audit`, `npm audit` | No se pudieron correr: sin acceso a Packagist ni npm | **Pendiente** (comandos en AUDITORIA A7) |
| Motor de la app: 25 pruebas | 25 pasan, 0 fallan, con Python 3.13 y un sustituto mínimo de pytest (sin acceso a PyPI) | Verificado parcialmente: **falta `python -m pytest` con Python 3.12 en el PC** |
| Transformación del **Excel real** local (`resultados escenario 2.xlsx`) | 4 hojas, 28.440 filas largas, 12 variables, 0 desconocidas, 0 errores | Verificado |
| Interfaz PySide6 | Se construyeron la ventana, las pantallas y los diálogos con un simulador de Qt, para descartar errores de Python. **No se ha abierto con Qt real** | **Pendiente** (`docs/prueba_manual_ui.md`, 14 pasos con capturas) |
| Ejecutable PyInstaller | No generado | **Pendiente** (`scripts/construir_exe.ps1`) |
| Destino SQL Server | Probado el fallo controlado sin pyodbc ni servidor. No hay pruebas contra un SQL Server real | Pendiente (opcional: SQL Server Express desechable) |
| GeoPackage de salida | Se lee de vuelta: 648 celdas, SRID 9377, WKT idéntico. No se abrió en QGIS | Pendiente (paso 13 de la prueba manual) |
| Contra la API real de SceneHub | No aplica: las rutas `/api/puente/v1` aún no existen | Pendiente (después de la COMPUERTA 1D) |

## 5. Comprobación paso a paso (para alguien no experto)
1. **SceneHub sin cambios:** en `C:\xampp\htdocs\SceneHub`, `git status` solo debe mostrar la carpeta nueva `docs/puente-anh/`.
2. **Línea base de SceneHub:** correr los comandos de AUDITORIA A7 y guardar las salidas.
3. **App:**
   ```powershell
   cd D:\Backups\PuentePython
   py -3.12 -m venv .venv
   .\.venv\Scripts\Activate.ps1
   pip install -r requirements-dev.txt
   python -m pytest
   python puente.py
   ```
   Debe verse «25 passed» y, al abrir, la ventana de Inicio.
4. Configuración → Modo demostración → Guardar → Probar conexión → Inicio → Buscar novedades → Ver detalle del Escenario 2 → «Cargar sin esta variable» → Simulacro → Cargar seleccionados → Sí, cargar → Historial → Exportar a Excel. Seguir `docs/prueba_manual_ui.md`.
5. **SQL Server (cuando la ANH confirme la versión):** ejecutar `docs/sqlserver_staging_borrador.sql` → Configuración → Activar SQL Server → servidor y base → Probar conexión → «Carga inicial al nuevo destino…».

## 6. Limitaciones y lo que no se pudo hacer
- Sin terminal en el PC: ni PHPUnit, ni build, ni auditorías de dependencias, ni pytest real, ni la interfaz con Qt real.
- Fixtures **provisionales y sintéticos**: imitan la estructura real del Excel, pero no sus valores. Los oficiales salen de la fase 1D.
- Los campos reales de la malla del PT1 (8 indicadores, agua y elevación) y el formato de `sitio_json` no se conocen hasta la reunión del 22-oct. La app conserva solo los indicadores que el catálogo marque como `territorio`.
- La definición WKT de EPSG:9377 no va dentro del GPKG de salida (se marca con el código EPSG; QGIS lo resuelve).

## 7. Dependencias y licencias
- **SceneHub:** paquetes en AUDITORIA A1; auditorías pendientes.
- **App:** `docs/dependencias_licencias.md`. requests (Apache-2.0), openpyxl (MIT), keyring (MIT), PySide6 (LGPL-3.0), pyodbc (MIT-0, por confirmar), pytest (MIT) y PyInstaller (GPL con excepción). No se usan pandas ni pyogrio. `pip-audit` queda pendiente.

## 8. Preparativos para SQL Server
**Listo:**
- contrato único;
- DDL staging en borrador, idempotente y sin funciones exclusivas de 2022, con geometry SRID 9377 e índice espacial con el recuadro;
- destino `sqlserver.py` con transacción única y rollback;
- estado por destino y carga inicial.

**Depende de la ANH:** versión y edición, collation, modelo de recuperación y límite de tamaño de Express.

## 9. Preguntas pendientes
Ver `PREGUNTAS_PENDIENTES.md`: de P-01 a P-18, cada una con su opción recomendada. Las urgentes son **P-01** (Unity), **P-02** y **P-03** (privacidad y archivos públicos), **P-04** (inmutabilidad), **P-08** (token) y **P-05** (plantilla del escenario 2).

## 10. Riesgos residuales
- Romper Unity al cambiar `/api/escenarios*` sin saber qué lee.
- Borrado accidental de un escenario publicado (hoy es físico y en cascada).
- Archivos de escenario descargables públicamente si existe `storage:link`.
- Límites de Hostinger para los archivos del PT1.
- Que la interfaz necesite ajustes al abrirla con Qt real.
