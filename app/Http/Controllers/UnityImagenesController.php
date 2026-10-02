<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * API libre (sin sesión, permisos ni CSRF) para que Unity cargue imágenes.
 *
 * Las imágenes se guardan en el disco "public":
 * storage/app/public/unity/{escenario_id|general}/{AAAA-MM-DD}/{archivo}
 * y quedan accesibles en /storage/unity/... después de `php artisan storage:link`.
 */
class UnityImagenesController extends Controller
{
    public const DISK = 'public';

    public const BASE_DIRECTORY = 'unity';

    public const MAX_FILES = 10;

    public const MAX_KILOBYTES = 10240; // 10 MB por imagen

    public const EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp'];

    public function store(Request $request): JsonResponse
    {
        // Acepta un archivo en "imagen" o varios en "imagenes[]".
        $files = array_values(array_filter(array_merge(
            [$request->file('imagen')],
            (array) $request->file('imagenes', [])
        )));

        $validator = Validator::make(
            ['escenario_id' => $request->input('escenario_id'), 'imagenes' => $files],
            [
                'escenario_id' => ['nullable', 'integer', 'exists:esceanarios,id'],
                'imagenes' => ['required', 'array', 'min:1', 'max:'.self::MAX_FILES],
                'imagenes.*' => ['file', 'mimes:'.implode(',', self::EXTENSIONS), 'max:'.self::MAX_KILOBYTES],
            ],
            [
                'imagenes.required' => 'Envía al menos una imagen en el campo "imagen" o "imagenes[]".',
                'imagenes.max' => 'Solo se admiten hasta '.self::MAX_FILES.' imágenes por solicitud.',
                'imagenes.*.mimes' => 'Formato no admitido. Usa: '.implode(', ', self::EXTENSIONS).'.',
                'imagenes.*.max' => 'Cada imagen puede pesar como máximo '.(self::MAX_KILOBYTES / 1024).' MB.',
                'imagenes.*.file' => 'La imagen no se recibió correctamente.',
                'escenario_id.exists' => 'El escenario indicado no existe.',
                'escenario_id.integer' => 'escenario_id debe ser un número entero.',
            ]
        );

        // Verifica el contenido real: el archivo debe poder leerse como imagen.
        $validator->after(function ($validator) use ($files) {
            foreach ($files as $index => $file) {
                if ($file instanceof UploadedFile && $file->isValid() && @getimagesize($file->getRealPath()) === false) {
                    $validator->errors()->add("imagenes.{$index}", 'El archivo "'.$file->getClientOriginalName().'" no es una imagen válida.');
                }
            }
        });

        // Respuesta JSON siempre, aunque Unity no envíe "Accept: application/json".
        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'mensaje' => $validator->errors()->first(),
                'errores' => $validator->errors(),
            ], 422);
        }

        $escenarioId = $request->filled('escenario_id') ? (int) $request->input('escenario_id') : null;
        $directory = self::BASE_DIRECTORY.'/'.($escenarioId ?? 'general').'/'.now()->format('Y-m-d');
        $disk = Storage::disk(self::DISK);
        $saved = [];

        try {
            foreach ($files as $file) {
                $path = $disk->putFileAs($directory, $file, $this->fileName($file));
                if ($path === false) {
                    throw new \RuntimeException('No se pudo escribir la imagen en el almacenamiento.');
                }
                $saved[] = [
                    'nombre' => basename($path),
                    'nombre_original' => $file->getClientOriginalName(),
                    'ruta' => $path,
                    'url' => $disk->url($path),
                    'mime_type' => $file->getMimeType(),
                    'tamano' => $file->getSize(),
                ];
            }
        } catch (\Throwable $e) {
            // Si una falla, no deja la carga a medias.
            $disk->delete(array_column($saved, 'ruta'));
            report($e);

            return response()->json(['ok' => false, 'mensaje' => 'No se pudieron guardar las imágenes.'], 500);
        }

        return response()->json([
            'ok' => true,
            'mensaje' => count($saved) === 1 ? 'Imagen guardada.' : count($saved).' imágenes guardadas.',
            'escenario_id' => $escenarioId,
            'total' => count($saved),
            'imagenes' => $saved,
        ], 201);
    }

    /**
     * Nombre seguro y único: slug del nombre original + marca de tiempo + sufijo aleatorio.
     * La extensión se toma del contenido real del archivo, no de la que envía el cliente.
     */
    private function fileName(UploadedFile $file): string
    {
        $base = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) ?: 'captura';
        $extension = strtolower($file->guessExtension() ?: $file->extension());
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;

        return Str::limit($base, 60, '').'_'.now()->format('His').'_'.Str::lower(Str::random(6)).'.'.$extension;
    }
}
