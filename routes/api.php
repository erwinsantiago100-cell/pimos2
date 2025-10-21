<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PedidoController;

// 1. RUTA BASE DE AUTENTICACIÓN (Ruta que viene por defecto)
// Esta ruta es usada por los clientes de la API para obtener los datos del usuario autenticado.
// GET /api/user
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 2. RUTAS DE GESTIÓN DE RECURSOS

// ProductoController: Proporciona las 5 rutas RESTful estándar (CRUD completo).
// Prefijo: /api/productos
Route::apiResource('productos', ProductoController::class);

// InventarioController: Rutas personalizadas para stock (solo INDEX, SHOW, UPDATE).
// Prefijo: /api/inventario
// Nota: Utilizamos PUT/PATCH y el ID del producto para actualizar el stock.
Route::get('inventario', [InventarioController::class, 'index']);
Route::get('inventario/{producto_id}', [InventarioController::class, 'show']);
Route::put('inventario/{producto_id}', [InventarioController::class, 'update']); 

// PedidoController: Proporciona las rutas RESTful para Pedidos, excluyendo la eliminación (destroy).
// El PedidoController maneja internamente la tabla 'detalles_pedidos'.
// Prefijo: /api/pedidos
Route::apiResource('pedidos', PedidoController::class)->except(['destroy']);

//Route::get('/user', function (Request $request) {
    //return $request->user();
//})->middleware('auth:sanctum');
