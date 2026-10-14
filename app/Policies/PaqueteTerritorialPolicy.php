<?php

namespace App\Policies;

use App\Models\PaqueteTerritorial;
use App\Models\User;

/**
 * Permisos del módulo territorial:
 * - territorio.ver: consultar paquetes y descargar archivos.
 * - territorio.subir: crear borradores, subir archivos y validar.
 * - territorio.publicar: confirmar advertencias, publicar y retirar.
 */
class PaqueteTerritorialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('territorio.ver');
    }

    public function view(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.ver');
    }

    public function create(User $user): bool
    {
        return $user->can('territorio.subir');
    }

    public function subir(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.subir') && $paquete->esEditable();
    }

    public function validar(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.subir') && $paquete->esEditable();
    }

    public function confirmar(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.publicar') && $paquete->esEditable();
    }

    public function publicar(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.publicar') && $paquete->estado === PaqueteTerritorial::VALIDADO;
    }

    public function retirar(User $user, PaqueteTerritorial $paquete): bool
    {
        return $user->can('territorio.publicar') && $paquete->estado === PaqueteTerritorial::PUBLICADO;
    }
}
