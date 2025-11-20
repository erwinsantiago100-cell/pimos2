<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

// Importar el recurso de inventario
use App\Http\Resources\InventarioResource; 

class ProductoResource extends JsonResource
{
    /**
     * Transforma el recurso en un array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tipo' => 'producto',
            'atributos' => [
                'nombre' => $this->nombre_gomita,
                'sabor' => $this->sabor,
                'tamano' => $this->tamano, // CLAVE CORREGIDA: Usando 'tamano' sin tilde para coincidir con el test.
                'precio' => (float) $this->precio, 
            ],
            'relaciones' => [
                // Usa la relación HasMany y el InventarioResource corregido.
                'inventario' => $this->whenLoaded('inventario', function () {
                    if ($this->inventario->isNotEmpty()) {
                         // Usar InventarioResource para formatear el primer registro.
                         return new InventarioResource($this->inventario->first());
                    }
                    return null;
                }),
            ],
        ];
    }
}