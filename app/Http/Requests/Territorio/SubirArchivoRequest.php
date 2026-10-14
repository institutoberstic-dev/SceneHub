<?php

namespace App\Http\Requests\Territorio;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubirArchivoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('subir', $this->route('paquete'));
    }

    public function rules(): array
    {
        $tipo = config('territorio.tipos.'.$this->input('tipo'));
        $maxKb = ($tipo['max_mb'] ?? 5) * 1024;

        return [
            'tipo' => ['required', 'string', Rule::in(array_keys(config('territorio.tipos')))],
            'archivo' => ['required', 'file', 'max:'.$maxKb],
            'descripcion' => ['nullable', 'required_if:tipo,otro', 'string', 'max:255'],
            'capa' => ['nullable', 'string', 'max:120', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'sha256_declarado' => ['nullable', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
            'fuente' => ['nullable', 'string', 'max:255'],
            'licencia' => ['nullable', 'string', 'max:255'],
            'fecha_captura' => ['nullable', 'date', 'before_or_equal:today'],
            'es_sensible' => ['nullable', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            $tipo = config('territorio.tipos.'.$this->input('tipo'));
            $archivo = $this->file('archivo');
            if (! $tipo || ! $archivo) {
                return;
            }
            $extension = strtolower($archivo->getClientOriginalExtension());
            if (! in_array($extension, $tipo['extensiones'], true)) {
                $validator->errors()->add('archivo', 'Para «'.$tipo['nombre'].'» solo se admiten archivos .'.implode(', .', $tipo['extensiones']).'.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'archivo.max' => 'El archivo supera el tamaño máximo permitido para este tipo.',
            'descripcion.required_if' => 'Describa el archivo cuando el tipo es «Otro».',
            'sha256_declarado.regex' => 'La huella SHA-256 debe tener 64 caracteres hexadecimales.',
            'capa.regex' => 'El nombre de la capa solo admite letras, números, guion y guion bajo.',
        ];
    }
}
