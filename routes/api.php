<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Importar los controladores de la API
use App\Http\Controllers\Api\ProductoController;
use App\Http\Controllers\Api\InventarioController;
use App\Http\Controllers\Api\PedidoController;

/*
|--------------------------------------------------------------------------
| Rutas de la API
|--------------------------------------------------------------------------
| Aquí se registran las rutas de la API para la aplicación.
*/

// Middleware de autenticación de Sanctum (si aplica)
Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Rutas de Recursos (CRUD Completo)
// apiResource registra automáticamente: index, store, show, update, destroy
Route::apiResource('productos', ProductoController::class);
Route::apiResource('inventario', InventarioController::class);
Route::apiResource('pedidos', PedidoController::class);
