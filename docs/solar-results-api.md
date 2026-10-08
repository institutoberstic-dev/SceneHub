# Integración de resultados de simulación

La vista `/resultados` consulta escenarios usando el endpoint existente y espera:

`GET /api/escenarios/{escenario}/resultados-simulacion` (alias: `/resultados`, `/resultados-solares`)

Endpoint público de lectura. La carga de resultados se realiza mediante las rutas web con sesión y permisos. `/api/simu-solars` fue retirado. Los registros se guardan en la tabla `resultados_simulacion` (antes `solar_data`) y sus versiones en `data_versions` con `tipo = simulacion` (antes `solar`). El nombre de este archivo se conserva por referencias existentes.

Respuesta JSON (ejemplo de estructura; no son datos de producción):

```json
{
  "archivos": [
    {
      "id": 25,
      "nombre": "resultados escenario 1.xlsx",
      "muestreos": {
        "1": [{"tiempo_minutos": 1, "potencia_solar_w": 0}],
        "5": [{"tiempo_minutos": 5, "potencia_solar_w": 0}],
        "10": [{"tiempo_minutos": 10, "potencia_solar_w": 0}]
      }
    }
  ]
}
```

- Cada muestreo devuelve la serie completa de la hoja correspondiente, ordenada por tiempo, sin duplicados ni paginación silenciosa. El importador admite hasta 10.000 registros por hoja y la vista pagina la tabla de 50 en 50. Para series mayores se debe acordar paginación y detección de eventos en servidor.
- `archivos: []` significa que no hay archivos compatibles. Una hoja sin registros se representa con `[]`.
- El nombre del archivo no importa para reconocerlo. Un libro es «resultado de simulación» si contiene al menos dos de las hojas Minutos, Cada5min, Cada10min y Horas; desde ese momento se valida de forma estricta y, si falla, la carga completa se rechaza (422) con mensajes por hoja, fila, columna y celda (máximo 12 por archivo). Los libros sin esas hojas (p. ej. `Datos comunidad 1.xlsx`) se guardan como documentos descargables; los archivos cuyo nombre dice «informe» o «reporte» (Word, PDF…) se guardan como informes.
- Al guardarlo, el libro se renombra a `resultados escenario {número del escenario}.xlsx` (por eso `nombre` muestra ese nombre). Subirlo de nuevo con otro nombre y otros datos lo reemplaza en una versión nueva; con los mismos datos no se guarda nada. Solo se admite un libro de resultados por carga. Los informes se guardan como `informe escenario {n}.{extensión}` y solo se actualizan con un archivo más reciente (detalle en `unity-data-api.md`).
- `POST /escenarios-analizar` (sesión; permiso `escenarios.crear` o `escenarios.versionar`, y owner si se envía `escenario_id`) analiza `archivos[]` sin guardarlos: tipo reconocido, errores de estructura, formato y registros por hoja, número en el nombre, `sha256`, archivos que ya tiene el escenario y número/nombre sugeridos. Lo usa la carga con arrastrar y soltar.
- Formatos admitidos: básico de 13 columnas (`Resultados caso 1.xlsx`) y extendido de 18 columnas (`resultados escenario 1 1.xlsx`), que añade % estado de carga, Wh acum. excedente no aprovechado, Wh acum. entregados por el diésel, L acum. de combustible y Wh acum. de demanda no cubierta. Las columnas se ubican por encabezado (el orden no importa); la primera puede llamarse Hora o Minuto. Las cuatro hojas deben tener las mismas columnas y no se admiten columnas desconocidas.
- Reglas por valor: tiempo entero, múltiplo del intervalo de la hoja y sin repetir (Horas se convierte a minutos); números con punto decimal; caudal, radiación, viento, potencia solar, energía almacenada y consumo no negativos; % estado de carga entre 0 y 100; acumulados (agua, salmuera, lodos, Wh y L acumulados) no negativos y no decrecientes en el tiempo; lodos enteros. Las celdas vacías se guardan como `null`. Las celdas con fórmula usan el valor calculado que guardó Excel. Máximo 10.000 registros por hoja.
- Las claves numéricas de las mediciones son las exportadas en `resources/js/components/solarSampling.js`. Valores desconocidos se representan con `null`, nunca cero. Cada versión informa `variables_disponibles` para que la vista solo ofrezca las variables con datos.
- `tiempo_minutos` es tiempo transcurrido de simulación, no fecha de recepción.
- «Energía almacenada (W)» se publica como `energia_almacenada_wh`: el informe del Escenario 1 confirma que el valor está en Wh (500 kWh = 500.000 Wh). `energia_almacenada_original` se conserva con el mismo valor por compatibilidad.

## Prueba manual

1. Ingresar como propietario de escenario (cliente).
2. Crear un escenario con el Excel o subirlo como contenido a uno existente. También se admite mediante actualización del escenario.
3. Abrir Resultados, Solar; seleccionar escenario y archivo. Pulsar Actualizar si la vista estaba abierta durante la carga.
4. Con `Resultados caso 1.xlsx` o `resultados escenario 1 1.xlsx`, comprobar 1.800, 360, 180 y 30 registros en las cuatro pestañas. Horas se convierte a minutos. Con el formato extendido aparecen además las variables de batería y diésel.
5. Seleccionar potencia solar e inicio de generación, ajustar umbral y revisar los tiempos. Las observaciones de la hoja por minuto son la referencia de mayor resolución.
6. Repetir el archivo actual: no debe duplicarse. Cambiar una celda numérica por texto debe rechazar la carga indicando hoja, fila y columna. Restaurar un archivo histórico después de otro resultado crea una nueva versión. El manifiesto de datos detecta cambios mediante el contenido normalizado.
7. Subir un archivo de emociones de detalle o promedio para comprobar su flujo habitual. Esos registros permanecen en sus tablas y no aparecen en Solar.

Las pruebas automatizadas usan SQLite en memoria y una carpeta temporal. El libro original puede validarse con `SOLAR_SAMPLE_FILE` apuntando a su ruta; no se incorpora a los datos reales del usuario.

## Detección en la interfaz

Cada hoja se analiza por separado antes de aplicar filtros de tiempo. No se detecta transición a través de datos faltantes o saltos en el intervalo esperado. El primer registro no se presenta como inicio si no tiene predecesor.

Para potencia solar, inicio = pasar de un valor menor o igual al umbral a uno superior. Fin = transición inversa. Para potencia neta se detectan cruces de cero. Las demás variaciones se marcan si la diferencia absoluta supera el umbral, en las unidades de la variable. El umbral inicial es cero y lo controla el usuario; no representa un criterio físico aprobado.

La referencia de primer inicio solar usa la hoja por minuto completa, aunque se consulte otra hoja o rango. Las variaciones en acumulados indican incrementos entre muestras; no se interpretan como tasas ni como cambios de ritmo. Falta un criterio físico acordado para detectar ritmos de producción.
