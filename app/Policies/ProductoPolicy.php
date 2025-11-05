<?php

namespace App\Policies;

use App\Models\Producto;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProductoPolicy
{
    /**
     * Helper para verificar si el usuario es Administrador.
     * Modifica esta lógica según tu sistema de roles.
     */
    private function isAdmin(User $user): bool
    {
        // Ejemplo: Asumir que el admin tiene un email específico
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver la lista de productos.
     */
    public function viewAny(User $user): bool
    {
        // Cualquier usuario autenticado puede ver la lista de productos
        return true;
    }

    /**
     * Determina si el usuario puede ver un producto específico.
     */
    public function view(User $user, Producto $producto): bool
    {
        // Cualquier usuario autenticado puede ver un producto
        return true;
    }

    /**
     * Determina si el usuario puede crear productos.
     */
    public function create(User $user): bool
    {
        // Solo los administradores pueden crear productos
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede actualizar un producto.
     */
    public function update(User $user, Producto $producto): bool
    {
        // Solo los administradores pueden actualizar productos
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede eliminar un producto.
     */
    public function delete(User $user, Producto $producto): bool
    {
        // Solo los administradores pueden eliminar productos
        return $this->isAdmin($user);
    }
}