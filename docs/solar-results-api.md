# Integración de resultados solares

La vista `/resultados` consulta escenarios usando el endpoint existente y espera:

`GET /api/escenarios/{escenario}/resultados-solares`

Endpoint implementado con autenticación, permiso `escenarios.leer` y autorización de lectura del escenario (igual que el detalle del escenario). No usa `/api/simu-solars`: no tiene asociación a escenario, archivo ni tiempo simulado.

Respuesta JSON (ejemplo de estructura; no son datos de producción):

```json
{
  "archivos": [
    {
      "id": 25,
      "nombre": "Resultados caso 1.xlsx",
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
- Identificar archivos por hojas y columnas, no por nombre. Conservar las tres hojas y la relación con el archivo del escenario. Reimportaciones deben ser idempotentes.
- Las claves numéricas de las mediciones son las exportadas en `resources/js/components/solarSampling.js`. Valores desconocidos se representan con `null`, nunca cero.
- `tiempo_minutos` es tiempo transcurrido de simulación, no fecha de recepción.
- No mapear la columna «Energía almacenada (W)» a `energia_almacenada_wh` hasta confirmar y convertir su unidad. Mientras tanto devolver null y conservar el dato original en la importación.
- Confirmar el carácter acumulado de agua, salmuera y paquetes de lodos en el importador.

## Prueba manual

1. Ingresar como propietario de escenario (cliente).
2. Crear un escenario con el Excel o subirlo como contenido a uno existente. También se admite mediante actualización del escenario.
3. Abrir Resultados, Solar; seleccionar escenario y archivo. Pulsar Actualizar si la vista estaba abierta durante la carga.
4. Con `Resultados caso 1.xlsx`, comprobar 1.800, 360 y 180 registros en las pestañas respectivas. La hoja Horas no se importa.
5. Seleccionar potencia solar e inicio de generación, ajustar umbral y revisar los tiempos. Las observaciones de la hoja por minuto son la referencia de mayor resolución.
6. Repetir la carga del mismo archivo, incluso con otro nombre: debe responder que no hay cambios y conservar un solo conjunto de mediciones. Si Excel vuelve a guardar el contenido y cambia sus bytes, se considera una nueva carga.
7. Subir un archivo de emociones de detalle o promedio para comprobar su flujo habitual. Esos registros permanecen en sus tablas y no aparecen en Solar.

Las pruebas automatizadas usan SQLite en memoria y una carpeta temporal. El libro original puede validarse con `SOLAR_SAMPLE_FILE` apuntando a su ruta; no se incorpora a los datos reales del usuario.

## Detección en la interfaz

Cada hoja se analiza por separado antes de aplicar filtros de tiempo. No se detecta transición a través de datos faltantes o saltos en el intervalo esperado. El primer registro no se presenta como inicio si no tiene predecesor.

Para potencia solar, inicio = pasar de un valor menor o igual al umbral a uno superior. Fin = transición inversa. Para potencia neta se detectan cruces de cero. Las demás variaciones se marcan si la diferencia absoluta supera el umbral, en las unidades de la variable. El umbral inicial es cero y lo controla el usuario; no representa un criterio físico aprobado.

La referencia de primer inicio solar usa la hoja por minuto completa, aunque se consulte otra hoja o rango. Las variaciones en acumulados indican incrementos entre muestras; no se interpretan como tasas ni como cambios de ritmo. Falta un criterio físico acordado para detectar ritmos de producción.
