<?php

/*
|--------------------------------------------------------------------------
| Catálogo de variables de los resultados de simulación
|--------------------------------------------------------------------------
| Se expone en la API de solo lectura (/api/puente/catalogo).
| Las unidades deben coincidir con DataVersionsController::UNITS (contrato con
| Unity); tests/Feature/PuenteApiTest lo comprueba para que no se desalineen.
| 'rango_esperado' usa el formato "min..max" (vacío = sin límite).
*/

$intervalos = '1|5|10|60';
$origen = 'DataVersionsController::UNITS · SimulationResultsImport::COLUMNS';

$v = fn (string $clave, string $nombre, string $unidad, string $descripcion, string $tipo, string $rango, array $encabezados) => [
    'clave' => $clave,
    'nombre_legible' => $nombre,
    'unidad' => $unidad,
    'descripcion' => $descripcion,
    'tipo_dato' => $tipo,
    'rango_esperado' => $rango,
    'intervalo' => $intervalos,
    'origen_en_codigo' => $origen,
    'ambito' => 'escenario',
    'encabezados_excel' => $encabezados,
];

return [
    'variables' => [
        $v('caudal', 'Caudal', 'm3/h', 'Caudal de agua de la planta', 'decimal', '0..', ['Caudal (m3/h)']),
        $v('radiacion_solar', 'Radiación solar', 'W/m2', 'Radiación solar incidente', 'decimal', '0..', ['Radiación solar (W/m2)']),
        $v('temperatura', 'Temperatura', '°C', 'Temperatura ambiente', 'decimal', '', ['Temperatura (°C)', 'Teperatura (°C)']),
        $v('velocidad_viento', 'Velocidad del viento', 'm/s', 'Velocidad del viento', 'decimal', '0..', ['Velocidad del viento (m/s)']),
        $v('potencia_solar', 'Potencia solar', 'W', 'Potencia generada por los paneles', 'decimal', '0..', ['Potencia solar (W)']),
        $v('potencia_neta', 'Potencia neta', 'W', 'Potencia generada menos consumida (puede ser negativa)', 'decimal', '', ['Potencia neta (W)']),
        $v('energia_almacenada', 'Energía almacenada', 'Wh', 'Energía en la batería (el Excel la rotula W; el informe del Escenario 1 confirma Wh)', 'decimal', '0..', ['Energía almacenada (W)', 'Energía almacenada (Wh)']),
        $v('consumo_planta', 'Consumo planta', 'W', 'Potencia consumida por la planta', 'decimal', '0..', ['Consumo planta (W)']),
        $v('agua_desalinizada', 'Agua desalinizada (acumulada)', 'm3', 'Volumen acumulado de agua desalinizada', 'decimal', '0..', ['Agua desalinizada (m3)']),
        $v('salmuera', 'Salmuera (acumulada)', 'm3', 'Volumen acumulado de salmuera', 'decimal', '0..', ['Salmuera (m3)']),
        $v('lodos_gruesos', 'Lodos gruesos (acumulados)', 'paquetes de 10 kg', 'Paquetes acumulados de lodos gruesos', 'entero', '0..', ['Lodos gruesos (N.º paquetes de 10 kg)']),
        $v('lodos_finos', 'Lodos finos (acumulados)', 'paquetes de 10 kg', 'Paquetes acumulados de lodos finos', 'entero', '0..', ['Lodos finos (N.º paquetes de 10 kg)']),
        $v('estado_carga', 'Estado de carga de la batería', '%', 'Porcentaje de carga de la batería (formato extendido)', 'decimal', '0..100', ['% estado de carga', 'Estado de carga (%)']),
        $v('excedente_no_aprovechado', 'Excedente no aprovechado (acumulado)', 'Wh', 'Energía acumulada no aprovechada (formato extendido)', 'decimal', '0..', ['Wh acum. excedente no aprovechado']),
        $v('energia_diesel', 'Energía entregada por el diésel (acumulada)', 'Wh', 'Energía acumulada del generador diésel (formato extendido)', 'decimal', '0..', ['Wh acum. entregados por el diesel']),
        $v('combustible_diesel', 'Combustible diésel (acumulado)', 'L', 'Litros acumulados de combustible (formato extendido)', 'decimal', '0..', ['L acum. de combustible']),
        $v('demanda_no_cubierta', 'Demanda no cubierta (acumulada)', 'Wh', 'Energía acumulada de demanda no cubierta (formato extendido)', 'decimal', '0..', ['Wh acum. de demanda no cubierta']),
    ],
];
