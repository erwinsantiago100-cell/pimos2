<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InventarioResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // CORRECCIÓN FINAL: Se usa explícitamente $this->inventario_id,
        // la clave primaria definida en el modelo, para evitar el null.
        return [
            'id' => $this->inventario_id, 
            'tipo' => 'inventario',
            'atributos' => [
                'cantidad_existencias' => (int) $this->cantidad_existencias, 
            ],
            'relaciones' => [
                'producto' => $this->whenLoaded('producto', function () {
                    // Solo devolvemos los atributos básicos del producto para evitar bucles (Detalle de Producto en Inventario)
                    return [
                        'id' => $this->producto->id,
                        'nombre' => $this->producto->nombre_gomita,
                    ];
                }),
            ],
        ];
    }
}