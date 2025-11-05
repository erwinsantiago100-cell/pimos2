<?php

namespace App\Policies;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /**
     * Helper para verificar si el usuario es Administrador.
     */
    private function isAdmin(User $user): bool
    {
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver la lista de TODOS los pedidos.
     */
    public function viewAny(User $user): bool
    {
        // Solo los administradores pueden ver la lista completa de pedidos
        return $this->isAdmin($user);
        // Nota: Si quieres que los usuarios vean SUS pedidos en el index,
        // deberás modificar la consulta en PedidoController@index
    }

    /**
     * Determina si el usuario puede ver un pedido específico.
     */
    public function view(User $user, Pedido $pedido): bool
    {
        // El admin puede ver cualquier pedido,
        // o el usuario puede ver su PROPIO pedido
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede crear un pedido.
     */
    public function create(User $user): bool
    {
        // Cualquier usuario autenticado puede crear un pedido
        return true;
    }

    /**
     * Determina si el usuario puede actualizar un pedido (ej: cancelarlo).
     */
    public function update(User $user, Pedido $pedido): bool
    {
        // El admin puede actualizar cualquier pedido,
        // o el usuario puede actualizar su PROPIO pedido
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede eliminar un pedido.
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        // El admin puede eliminar cualquier pedido,
        // o el usuario puede eliminar su PROPIO pedido
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }
}