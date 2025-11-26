<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PedidoController;
// use App\Http\Controllers\Api\AuthController;
// Se elimina la importación de AuthController

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí es donde puedes registrar rutas API para tu aplicación.
|
*/

// 1. RUTAS DE AUTENTICACIÓN (Ahora en InventarioController)
Route::post('login', [InventarioController::class, 'login']);

// RUTA BASE DE AUTENTICACIÓN (Ruta que viene por defecto)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// 2. RUTAS PROTEGIDAS (Requieren autenticación)
Route::middleware('auth:sanctum')->group(function () {
    // Cerrar Sesión (Logout) - Ahora en InventarioController
    Route::post('logout', [InventarioController::class, 'logout']);

    // ProductoController: Proporciona las 5 rutas RESTful estándar (CRUD completo).
    // Genera: index, store, show, update, destroy
    Route::apiResource('productos', ProductoController::class);

    // InventarioController: Rutas Resource para stock (index, show, update, destroy).
    Route::apiResource('inventario', InventarioController::class)->only([
        'index', // GET /api/inventario
        'show',  // GET /api/inventario/{inventario} -> mapea a producto_id
        'update', // PUT/PATCH /api/inventario/{inventario} -> mapea a producto_id
        'destroy', // DELETE /api/inventario/{inventario} -> mapea a producto_id
    ]);

    // PedidoController: Proporciona las rutas RESTful para Pedidos, excluyendo la eliminación (destroy).
    // Genera: index, store, show, update
    Route::apiResource('pedidos', PedidoController::class)->except(['destroy']);
    Route::delete('pedidos/{pedido}', [PedidoController::class, 'destroy'])
     ->name('pedidos.destroy');
});