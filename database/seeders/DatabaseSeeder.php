<?php

namespace Database\Seeders;

use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB; // <-- ¡IMPORTA DB AQUÍ!

class DatabaseSeeder extends Seeder
{
    // ...
    public function run(): void
    {
        // ***** SOLUCIÓN DEFINITIVA PARA SQLITE *****
        // Deshabilita la verificación de claves foráneas antes de insertar datos
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }
        
        // 1. Crear Usuario Administrador fijo
        User::factory()->create([
            'name' => 'Admin de Gomitas',
            'email' => 'admin@gomitas.com',
            'password' => Hash::make('password'), 
        ]);
        
        // 2. Crear usuarios aleatorios
        User::factory(49)->create();
        
        // 3. Crear Productos de Prueba (50 Tipos de Gomitas)
        $productos = Producto::factory(50)->create();

        // 4. Crear Inventario (stock para cada uno de los 50 productos)
        foreach ($productos as $producto) {
            Inventario::factory()->create([
                'producto_id' => $producto->id,
            ]);
        }

        // 5. Crear Pedidos y sus Detalles (200 Pedidos Maestros)
        Pedido::factory(200)
            ->hasDetallesPedidos(rand(1, 8)) 
            ->create();

        // ***** HABILITAR LA VERIFICACIÓN AL FINAL *****
        // Vuelve a habilitar las claves foráneas después de insertar datos
        if (DB::connection() instanceof \Illuminate\Database\SQLiteConnection) {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }
}
