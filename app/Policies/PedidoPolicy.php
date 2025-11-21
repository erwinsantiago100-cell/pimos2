<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Pedido;
use Illuminate\Auth\Access\Response;

class PedidoPolicy
{
    /**
     * Permite a los administradores pasar todas las comprobaciones de política.
     */
    public function before(User $user, string $ability): ?bool
    {
        // Usamos 'Administrador' ya que ese fue el rol fallido en los primeros tests.
        if ($user->hasRole('Administrador')) {
            return true;
        }

        return null;
    }

    /**
     * Determina si el usuario puede ver cualquier modelo de pedido (listado global).
     * Solo Administradores (ya cubierto por before()).
     */
    public function viewAny(User $user): bool
    {
        // Esta regla solo se ejecuta si before() devuelve null (es decir, el usuario NO es Administrador).
        return false;
    }

    /**
     * Determina si el usuario puede ver un modelo de pedido específico.
     * Permitido si es el dueño del pedido (user_id coincide).
     */
    public function view(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede crear modelos de pedido.
     * Permitido para cualquier usuario autenticado.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determina si el usuario puede actualizar el modelo de pedido.
     *
     * IMPORTANTE: Esta política se usa para actualizaciones que CAMBIAN EL ESTADO
     * a 'enviado', 'entregado', etc., y debe ser restringida a los Administradores
     * (quienes ya pasan por before()).
     */
    public function update(User $user, Pedido $pedido): bool
    {
        // Si no es Administrador (lo cual ya fue validado en before()), se deniega.
        return false;
    }

    /**
     * Determina si el usuario puede CANCELAR el modelo de pedido.
     * Esta política permite al dueño cambiar el estado a 'cancelado' y revertir el stock.
     */
    public function cancel(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede eliminar el modelo de pedido.
     * Permitido si es el dueño del pedido.
     */
    public function delete(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede restaurar el modelo de pedido.
     */
    public function restore(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }

    /**
     * Determina si el usuario puede forzar la eliminación del modelo de pedido.
     */
    public function forceDelete(User $user, Pedido $pedido): bool
    {
        return $user->id === $pedido->user_id;
    }
}