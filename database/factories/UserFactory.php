<?php

namespace Database\Factories;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash; // ¡Asegúrate de que esta esté!
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password; // <-- ¡Esta línea es CRUCIAL!

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Usamos unique() para los 49 usuarios
            'email' => fake()->unique()->safeEmail(), 
            'email_verified_at' => now(),
            
            // ¡Esta línea aplica el HASH de la contraseña!
            'password' => static::$password ??= Hash::make('password'), 
            
            'remember_token' => Str::random(10),
        ];
    }
    
    // ... (El resto de la clase, como el método unverified)
}
