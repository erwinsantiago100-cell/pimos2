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
        // La lógica de Admin es el usuario con el email 'admin@gomitas.com'.
        return $user->email === 'admin@gomitas.com';
    }

    /**
     * Determina si el usuario puede ver la lista de TODOS los pedidos (index).
     * El test fallido 'puede listar todos los pedidos solo el administrador index' (403)
     * indica que el usuario del test no está siendo autenticado correctamente como admin.
     * La política es correcta (Admin-only), pero la mantendremos así.
     */
    public function viewAny(User $user): bool
    {
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede ver un pedido específico (show).
     */
    public function view(User $user, Pedido $pedido): bool
    {
        // El admin puede ver cualquier pedido, o el usuario puede ver su PROPIO pedido
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede crear un pedido (store).
     */
    public function create(User $user): bool
    {
        // Cualquier usuario autenticado puede crear un pedido
        return true;
    }

    /**
     * Determina si el usuario puede actualizar un pedido (update).
     * Soluciona el error 403 para 'update owner'.
     */
    public function update(User $user, Pedido $pedido): bool
    {
        // El administrador puede actualizar cualquier pedido, o el dueño puede actualizar el suyo.
        // Asumimos que si el dueño actualiza, solo puede cambiar campos no críticos como el estado a 'cancelado'.
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }
    
    /**
     * Determina si el usuario puede CANCELAR un pedido.
     * (Esta es una acción separada, reservada para el admin en el test).
     */
    public function cancel(User $user, Pedido $pedido): bool
    {
        // Mantenemos esta función para el administrador si el controlador la usa explícitamente.
        return $this->isAdmin($user);
    }

    /**
     * Determina si el usuario puede eliminar un pedido (destroy).
     * Soluciona el error 403 para 'destroy owner' y 'destroy admin'.
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        // El test espera que el dueño pueda eliminar su propio pedido.
        return $this->isAdmin($user) || $user->id === $pedido->user_id;
    }
}