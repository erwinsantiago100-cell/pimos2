<?php

namespace Database\Factories;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Factories\Factory;
/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            //
            'nombre_gomita' => $this->faker->unique()->word() . ' Enchilada',
            'sabor' => $this->faker->randomElement(['Mango', 'Piña', 'Sandía', 'Tamarindo', 'Fresa', 'Durazno']),
            'tamano' => $this->faker->randomElement(['Pequeña', 'Grande', 'Caja']),
            // Genera un precio entre 5.00 y 150.00 con 2 decimales
            'precio' => $this->faker->randomFloat(2, 5, 150),
        ];
    }
}
