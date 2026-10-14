<?php

namespace App\Http\Requests\Territorio;

use Illuminate\Foundation\Http\FormRequest;

class RetirarPaqueteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('retirar', $this->route('paquete'));
    }

    public function rules(): array
    {
        return ['motivo' => ['required', 'string', 'min:10', 'max:2000']];
    }

    public function messages(): array
    {
        return ['motivo.min' => 'Explique el motivo del retiro (al menos 10 caracteres).'];
    }
}
