<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="DetallePedidoResource",
 * title="Detalle de Pedido (Resource)",
 * description="Representación del detalle de un pedido, incluyendo el producto y las cantidades.",
 * @OA\Property(property="id", type="integer", example=1),
 * @OA\Property(property="tipo", type="string", example="detalle_pedido"),
 * @OA\Property(
 * property="atributos",
 * type="object",
 * @OA\Property(property="cantidad", type="integer", example=2),
 * @OA\Property(property="precio_unitario", type="number", format="float", example=15.50),
 * @OA\Property(property="subtotal", type="number", format="float", example=31.00)
 * ),
 * @OA\Property(
 * property="relaciones",
 * type="object",
 * @OA\Property(property="producto_id", type="integer", example=101),
 * @OA\Property(
 * property="producto",
 * type="object",
 * description="Información básica del producto (solo si se carga)",
 * @OA\Property(property="id", type="integer", example=101),
 * @OA\Property(property="tipo", type="string", example="producto"),
 * @OA\Property(property="nombre", type="string", example="Gomita de oso"),
 * @OA\Property(property="sabor", type="string", example="Fresa")
 * )
 * )
 * )
 */
class DetallePedidoResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => 'detalle_pedido',
            'atributos' => [
                'cantidad' => (int) $this->cantidad,
                'precio_unitario' => (float) $this->precio_unitario,
                'subtotal' => (float) $this->cantidad * $this->precio_unitario,
            ],
            'relaciones' => [
                // Incluimos el ID del producto para visibilidad directa
                'producto_id' => $this->producto_id, 
                
                // Incluimos la información básica del producto
                'producto' => $this->whenLoaded('producto', function () {
                    return [
                        'id' => $this->producto->id,
                        'tipo' => 'producto',
                        // --- ERROR CORREGIDO AQUÍ: Se cambió 'nombre_gomita' por 'nombre' ---
                        'nombre' => $this->producto->nombre, 
                        'sabor' => $this->producto->sabor,
                    ];
                }),
            ],
        ];
    }
}