# Guía de uso · Sistema territorial (paquete del PT1)

Para el equipo de Sistema Territorial (PT1 / GAST) y para quien revisa y publica. Capturas: **pendientes** (se toman en la prueba local, ver el final).

## Quién puede hacer qué

| Módulo asignado en Usuarios | Permite |
|---|---|
| **Territorio** | Ver paquetes, crear borradores, subir y quitar archivos del borrador, «Validar paquete» |
| **Publicar territorio** | Ver paquetes, confirmar advertencias, **publicar** y **retirar** |

Separar los dos módulos permite que una persona suba y otra revise.

## Pasos

1. **Menú → Territorio → «Nuevo paquete».** Escriba la versión (se sugiere la siguiente: v01, v02…) y unas notas. Queda como **borrador**.
2. **Subir archivos.** Arrastre cada archivo a la zona de carga, elija su tipo (se sugiere según el nombre) y, si los tiene, complete fuente, licencia, fecha de captura y SHA-256. Pulse «Subir y validar».
   - Obligatorios: malla de hexágonos (`.gpkg`, capa `malla_hex_1ha_gee`), valores de sitio (`.json`), escena 3D (`.glb`) y georreferencia (`.json`).
   - Opcionales: huellas, cercas, copas, superficies, corredores, infraestructura (`.gpkg` o `.geojson`), ortofoto (solo si su licencia lo permite) y «otro» (con descripción).
   - Si sube otra vez un tipo único (por ejemplo, la malla) mientras es borrador, el archivo anterior se reemplaza.
3. **Revisar las validaciones** (pestaña «Validaciones»). Cada archivo se revisa al subirlo:
   - extensión, tamaño, firma real del contenido y huella SHA-256;
   - GeoPackage: tablas del estándar, capa, EPSG:9377, `hex_id` sin vacíos ni repetidos y conteo de 648 celdas (y 91 edificaciones en las huellas);
   - tipo de geometría de cada capa (ANH IN-38, 5.1.4): todos los elementos con geometría y **un solo tipo** por capa (puntos, líneas o polígonos). Mezclar tipos es error; un tipo distinto del que espera el Puente de datos (malla y huellas: polígonos; cercas y corredores: líneas; copas y superficies: polígonos; infraestructura: puntos) es advertencia, salvo en la malla, donde es error;
   - GeoJSON: debe declarar el sistema de referencia EPSG:9377 (`"crs": {"type": "name", "properties": {"name": "urn:ogc:def:crs:EPSG::9377"}}`). Sin esa declaración, la norma lo interpreta en WGS84, así que es error: mejor entregarlo como GeoPackage;
   - JSON válido con sus claves mínimas;
   - firma glTF 2.0 de la escena.

   **Error** = hay que corregir el archivo en el origen y volver a subirlo. **Advertencia** = hay que confirmarla antes de publicar.
4. **«Validar paquete».** Revisa que estén todos los obligatorios. Sin errores, el paquete pasa a **validado**.
5. **Revisión humana** (módulo «Publicar territorio»): confirme las advertencias que correspondan (por ejemplo, un conteo distinto de 648 que se sabe correcto).
6. **«Publicar»** → confirmar. Desde ese momento la versión es **inmutable**: no se pueden subir, cambiar ni borrar archivos. La app «Puente de datos» ya puede copiarla.
7. Si algo estaba mal: **«Retirar»** con el motivo (la versión queda como historial) y cree una versión nueva.

La pestaña **Actividad** registra quién hizo qué y cuándo.

## Qué NO hace SceneHub

No muestra mapas, no reproyecta, no recalcula y no edita geometrías. Los archivos se guardan en almacenamiento **privado**: solo se descargan con sesión y permiso, o por la API `/api/puente/territorio/*` con un token de solo lectura (de la cobertura «territorio» o el del Puente de datos).

## Prueba local (para las capturas)

```powershell
php artisan migrate
php artisan db:seed --class=RolesAndPermissionsSeeder
npm run build
php artisan serve
```

Entrar como administrador, asignar a un usuario los módulos «Territorio» y «Publicar territorio», y recorrer los pasos 1 a 7 con archivos de prueba. Guardar capturas de: listado, borrador con archivos, validaciones con una advertencia, publicado y retirado.
