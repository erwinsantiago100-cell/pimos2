<?php

namespace Database\Factories;
use App\Models\Inventario;
use App\Models\Producto;
use App\Models\Pedido;
use App\Models\DetallePedido;
use Illuminate\Database\Eloquent\Factories\Factory;

class DetallePedidoFactory extends Factory
{
    protected $model = DetallePedido::class;

    public function definition(): array
    {
        // En tu Seeder creas 200 Pedidos (IDs 1-200) y 50 Productos (IDs 1-50).
        // Si usas el Seeder tal cual, necesitas asegurar que la Factory no use IDs fuera de ese rango.
        
        return [
            // El Pedido_ID puede ser hasta 200 (si creas 200 pedidos en el Seeder)
            'pedido_id' => $this->faker->numberBetween(1, 200), 
            
            // Producto_ID NO puede ser mayor a 50
            'producto_id' => $this->faker->numberBetween(1, 50), // <-- ¡Corregir este límite!
            
            'cantidad' => $this->faker->numberBetween(1, 10),
            'precio_unitario' => $this->faker->randomFloat(2, 5, 100),
        ];
    }
}
