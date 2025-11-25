<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="PedidoResource",
 * title="Pedido Resource",
 * description="Estructura de un Pedido individual, incluyendo sus relaciones.",
 * required={"id", "user_id", "total", "estado"},
 * @OA\Property(property="id", type="integer", example=1, description="ID único del pedido."),
 * @OA\Property(property="user_id", type="integer", example=10, description="ID del usuario que realizó el pedido."),
 * @OA\Property(property="total", type="number", format="float", example=150.75, description="Monto total del pedido."),
 * @OA\Property(
 * property="estado",
 * type="string",
 * enum={"pendiente", "enviado", "entregado", "cancelado"},
 * example="pendiente",
 * description="Estado actual del pedido."
 * ),
 * @OA\Property(property="fecha_creacion", type="string", format="date-time", example="2024-05-15T10:00:00Z", description="Fecha y hora de creación del pedido."),
 * @OA\Property(
 * property="detalles_pedido", 
 * type="array",
 * description="Lista de ítems incluidos en el pedido.",
 * @OA\Items(ref="#/components/schemas/DetallePedidoResource")
 * ),
 * @OA\Property(
 * property="usuario", 
 * ref="#/components/schemas/UserResource", 
 * description="Datos básicos del usuario asociado al pedido (si se carga la relación 'user')."
 * )
 * )
 */
class PedidoResource extends JsonResource
{
    /**
     * Transforma el pedido en una estructura JSON.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'total' => (float) $this->total,
            'estado' => $this->estado,
            'fecha_creacion' => $this->created_at?->toDateTimeString(),
            'fecha_actualizacion' => $this->updated_at?->toDateTimeString(),

            // Se asume que existen UserResource y DetallePedidoResource
            'usuario' => $this->whenLoaded('user', function () {
                return new UserResource($this->user);
            }),
            'detalles_pedido' => $this->whenLoaded('detallesPedidos', function () {
                return DetallePedidoResource::collection($this->detallesPedidos);
            }),
        ];
    }
}