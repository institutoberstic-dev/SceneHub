<?php

/*
| Descarga de la app de escritorio «Puente de datos» desde SceneHub.
|
| Quien tiene «Publicar app Puente» sube cada versión (instalador .exe o portable .zip)
| y quien tiene «Descargar app Puente» la descarga. Los archivos van al disco PRIVADO:
| solo se descargan con sesión y permiso. Una versión se retira, no se borra.
|
| Tamaño: además de este límite, PHP debe aceptar el archivo (upload_max_filesize y
| post_max_size en hPanel → Configuración de PHP).
*/

return [
    'disco' => 'local',
    'carpeta' => 'puente-app',
    'max_mb' => (int) env('PUENTE_APP_MAX_MB', 300),
    'tipos' => [
        'instalador' => [
            'nombre' => 'Instalador para Windows',
            'extensiones' => ['exe', 'msi'],
            'recomendado' => true,
        ],
        'portable' => [
            'nombre' => 'Portable (.zip, sin instalar)',
            'extensiones' => ['zip'],
            'recomendado' => false,
        ],
    ],
    'requisitos' => 'Windows 10 u 11 de 64 bits. Para el destino SQL Server: ODBC Driver 18 for SQL Server.',
];
