<?php

namespace Database\Factories;

use App\Models\Pedido;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Pedido>
 */
class PedidoFactory extends Factory
{
    protected $model = Pedido::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            // FK: Asigna un User ID existente al azar (asume que UserFactory existe)
            'user_id' => User::factory(), 
            // Genera un total entre 50.00 y 1500.00
            'total' => $this->faker->randomFloat(2, 50, 1500), 
            'estado' => $this->faker->randomElement(['pendiente', 'procesando', 'enviado', 'entregado']),
        ];
    }
}
