<?php

//1.1. productos
// Esta tabla es el catálogo principal de las gomitas que vendes.

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;


return new class extends Migration
{
    /**
     * Run the migrations.
     */

    //Función up(): Crea la tabla productos con las columnas mencionadas.
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_gomita');
            $table->string('sabor');
            $table->string('tamano');
            $table->decimal('precio', 8, 2);
            //En Laravel, en una migración, timestamps() es un método que agrega automáticamente dos columnas a la tabla:
             //created_at → guarda la fecha y hora en que el registro fue creado.

            //updated_at → guarda la fecha y hora en que el registro fue actualizado por última vez.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations. 
     */

    //Función down(): Elimina la tabla productos si se revierte la migración (rollback).
    public function down(): void
    {
        //
        Schema::dropIfExists('productos');
    }
};
