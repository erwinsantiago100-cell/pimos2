<?php

namespace App\Policies;

use App\Models\Inventario;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InventarioPolicy
{
    /**
     * Helper para verificar si el usuario es Administrador.
     */
    private function isAdmin(User $user): bool
    {
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver la lista de inventario.
     */
    public function viewAny(User $user): bool
    {
        // Solo los administradores pueden gestionar el inventario
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede ver un registro de inventario.
     */
    public function view(User $user, Inventario $inventario): bool
    {
        // Solo los administradores
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede crear registros de inventario.
     */
    public function create(User $user): bool
    {
        // Solo los administradores
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede actualizar un registro de inventario.
     */
    public function update(User $user, Inventario $inventario): bool
    {
        // Solo los administradores
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede eliminar un registro de inventario.
     */
    public function delete(User $user, Inventario $inventario): bool
    {
        // Solo los administradores
        return $this->isAdmin($user);
    }
}