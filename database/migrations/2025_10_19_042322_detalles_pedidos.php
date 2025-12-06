<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    //1.4. detalles_pedidos
    // Esta tabla es crucial, ya que almacena los detalles de lo que realmente se compró en cada pedido. 
    // Representa una relación Muchos a Muchos entre pedidos y productos.
    public function up(): void
    {
        Schema::create('detalles_pedidos', function (Blueprint $table) {
            $table->id();
            
            // CAMBIO 1: Usar unsignedBigInteger y references explícitamente para SQLite
            $table->unsignedBigInteger('pedido_id');
            $table->foreign('pedido_id')->references('id')->on('pedidos')->onDelete('cascade');
            
            // CAMBIO 2: Lo mismo para producto_id
            $table->unsignedBigInteger('producto_id');
            $table->foreign('producto_id')->references('id')->on('productos')->onDelete('cascade');
            
            $table->unsignedInteger('cantidad'); 
            $table->decimal('precio_unitario', 8, 2); 

            $table->timestamps();
        });
    } 

    /**
     * Reverse the migrations.
     */
    //Relaciones: Define dos claves foráneas explícitas (foreign en lugar de foreignId) 
//que apuntan a pedidos y productos. Esto es un buen ejemplo de cómo manejar tablas pivote o de relación.
// La directiva ->onDelete('cascade') asegura que si un usuario es eliminado,
// todos sus pedidos asociados también se eliminen automáticamente.
    public function down(): void
    {
        Schema::dropIfExists('detalles_pedidos');
    }
};
