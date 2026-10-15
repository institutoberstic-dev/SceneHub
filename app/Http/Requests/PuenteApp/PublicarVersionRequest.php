<?php

namespace App\Http\Requests\PuenteApp;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class PublicarVersionRequest extends FormRequest
{
    /** Firma real del contenido según la extensión (no basta con el nombre del archivo). */
    private const FIRMAS = [
        'exe' => 'MZ',
        'msi' => "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1",
        'zip' => "PK\x03\x04",
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('puente.publicar');
    }

    public function rules(): array
    {
        $tipos = array_keys(config('puente_app.tipos'));

        return [
            'version' => [
                'required', 'string', 'max:20', 'regex:/^\d+\.\d+\.\d+$/',
                Rule::unique('puente_versiones', 'version')->where('tipo', $this->input('tipo')),
            ],
            'tipo' => ['required', Rule::in($tipos)],
            'notas' => ['nullable', 'string', 'max:2000'],
            'archivo' => ['required', 'file', 'max:'.(config('puente_app.max_mb') * 1024)],
        ];
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'La versión debe tener el formato 1.0.0 (la misma de motor/__init__.py).',
            'version.unique' => 'Esa versión ya está publicada para ese tipo. Suba una versión nueva.',
            'archivo.max' => 'El archivo supera el máximo de '.config('puente_app.max_mb').' MB.',
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $archivo = $this->file('archivo');
            $tipo = config('puente_app.tipos.'.$this->input('tipo'));
            if (! $archivo || ! $tipo) {
                return;
            }
            $extension = strtolower($archivo->getClientOriginalExtension());
            if (! in_array($extension, $tipo['extensiones'], true)) {
                $validator->errors()->add('archivo', "Para «{$tipo['nombre']}» se admite: .".implode(', .', $tipo['extensiones']).'.');

                return;
            }
            $firma = self::FIRMAS[$extension];
            $manejador = fopen($archivo->getRealPath(), 'rb');
            $inicio = $manejador ? fread($manejador, strlen($firma)) : '';
            if ($manejador) {
                fclose($manejador);
            }
            if ($inicio !== $firma) {
                $validator->errors()->add('archivo', "El contenido no corresponde a un archivo .{$extension} válido.");
            }
        }];
    }
}
