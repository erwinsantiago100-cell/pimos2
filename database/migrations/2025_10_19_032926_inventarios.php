<?php


//  1.2. inventarios
// Esta tabla registra las existencias de cada producto en el inventario.
//Esta tabla se encarga de llevar el control de cuántas unidades de cada gomita tienes en stock.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */

    //Relación: producto_id está enlazada a la columna id de la tabla productos.
    // La directiva ->onDelete('cascade') significa que si se elimina un producto, 
    //automáticamente se eliminará el registro de inventario asociado a ese producto.
    public function up(): void
    {
        Schema::create('inventarios', function (Blueprint $table) {
            $table->id('inventario_id');
            // Clave foránea que referencia a 'productos'
            $table->foreignId('producto_id')->constrained('productos')->onDelete('cascade');
            $table->unsignedInteger('cantidad_existencias');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    //Función down(): Elimina la tabla inventarios si se revierte la migración (rollback).
    public function down(): void
    {
        //
        Schema::dropIfExists('inventarios');
    }
};
