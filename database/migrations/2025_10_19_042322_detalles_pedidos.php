<?php // <-- Asegúrate de tener esta etiqueta al inicio

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('detalles_pedidos', function (Blueprint $table) {
            $table->id();
            
            // Clave foránea que referencia a 'pedidos'
            $table->foreignId('pedido_id')->constrained('pedidos')->onDelete('cascade');
            
            // Clave foránea que referencia a 'productos'
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            
            $table->unsignedInteger('cantidad'); 
            $table->decimal('precio_unitario', 8, 2); 

            $table->timestamps();
        });
    } // <--- ¡Importante! Aquí cierra el método up()

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('detalles_pedidos');
    }
}; // <--- ¡Importante! Aquí cierra la clase