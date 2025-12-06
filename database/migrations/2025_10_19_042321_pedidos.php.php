<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

    //  1.3. pedidos
// Esta tabla registra los pedidos realizados por los usuarios.

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        
        //Relación: user_id está enlazada a la tabla users (asumiendo que ya existe o se creará).
//Función up(): Crea la tabla pedidos.
        Schema::create('pedidos', function (Blueprint $table) {
           $table->id(); // <-- ¡Esto crea la columna 'id' (el nombre por defecto)!
            // FK que enlaza a users
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total', 10, 2); // Total pagado
            $table->enum('estado', ['pendiente', 'procesando', 'enviado', 'entregado'])->default('pendiente');
            $table->timestamps();
    });
    }
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('pedidos');
    }
    
};


