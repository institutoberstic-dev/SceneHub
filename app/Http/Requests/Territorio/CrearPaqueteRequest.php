<?php

namespace App\Http\Requests\Territorio;

use App\Models\PaqueteTerritorial;
use Illuminate\Foundation\Http\FormRequest;

class CrearPaqueteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', PaqueteTerritorial::class);
    }

    public function rules(): array
    {
        return [
            'version' => ['required', 'string', 'regex:/^v\d{2,3}$/', 'unique:paquete_territorial,version'],
            'notas' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'version.regex' => 'La versión debe tener la forma v01, v02… (v y dos o tres dígitos).',
            'version.unique' => 'Ya existe un paquete con la versión :input.',
        ];
    }
}
