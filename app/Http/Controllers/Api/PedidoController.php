<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use App\Http\Resources\PedidoResource;
use App\Http\Resources\PedidoCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Requests\StorePedidoRequest; 
use App\Http\Requests\UpdatePedidoRequest; 

/**
 * Gestiona la lógica CRUD de los Pedidos.
 */
class PedidoController extends Controller
{
    /**
     * Define los middlewares de autorización basados en los permisos de Pedidos.
     */
    public function __construct()
    {
        // Permisos de Lectura (Admin, Editor)
        $this->middleware('can:pedidos.ver')->only(['index', 'show']);
        
        // Permiso de Creación (Concedido a Admin, Editor, Usuario)
        $this->middleware('can:pedidos.crear')->only('store');
        
        // Permiso de Procesamiento/Actualización de estado (Admin, Editor)
        $this->middleware('can:pedidos.procesar')->only('update');
        
        // Permiso de Cancelación/Eliminación (Solo Admin)
        $this->middleware('can:pedidos.cancelar')->only('destroy');
    }
    
    /**
     * Muestra una lista de todos los pedidos (GET /api/pedidos).
     */
    public function index()
    {
        try {
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los pedidos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Almacena un nuevo pedido y deduce el inventario (POST /api/pedidos).
     */
    public function store(StorePedidoRequest $request)
    {
        // Se mantiene la implementación de deducción de stock, usando StorePedidoRequest
        $validatedData = $request->validated();
        $total = 0;
        
        try {
            DB::beginTransaction();

            // Usar $validatedData['user_id'] si lo envías, si no usa auth()->id() o el default
            $pedido = Pedido::create([
                'user_id' => $validatedData['user_id'] ?? auth()->id() ?? 1, 
                'estado' => $validatedData['estado'] ?? 'pendiente', // Usar estado validado o 'pendiente'
                'total' => 0,
            ]);

            $detalles = [];
            foreach ($validatedData['detalles'] as $detalle) {
                // Bloquear el producto para asegurar que el inventario no cambie en otra transacción
                $producto = Producto::lockForUpdate()->find($detalle['producto_id']);
                
                if (!$producto) {
                    DB::rollBack();
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], 404);
                }

                $inventario = $producto->inventario->first();
                $cantidadSolicitada = (int) $detalle['cantidad'];
                $precioUnitario = (float) $producto->precio;
                $subtotal = $cantidadSolicitada * $precioUnitario;

                // Deducir Inventario
                if ($inventario && $inventario->cantidad_existencias >= $cantidadSolicitada) {
                    $inventario->cantidad_existencias -= $cantidadSolicitada;
                    $inventario->save();
                    $total += $subtotal;

                    $detalles[] = [
                        'producto_id' => $producto->id,
                        'cantidad' => $cantidadSolicitada,
                        'precio_unitario' => $precioUnitario,
                    ];
                } else {
                    DB::rollBack();
                    $stock = $inventario ? $inventario->cantidad_existencias : 0;
                    return response()->json([
                        'error' => 'Stock insuficiente para ' . $producto->nombre_gomita,
                        'disponible' => $stock
                    ], 400);
                }
            }

            // Actualizar detalles y total
            $pedido->detallesPedidos()->createMany($detalles);
            $pedido->total = $total;
            $pedido->save();

            DB::commit();

            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido creado y stock actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear el pedido.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un pedido específico (GET /api/pedidos/{id}).
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * La cancelación requiere el permiso 'pedidos.cancelar', pero la función base está protegida por 'pedidos.procesar'.
     * Si un Editor intenta cancelar, el middleware 'pedidos.procesar' lo dejará pasar, 
     * pero la lógica interna de la función debe ser reforzada si hay una lógica muy estricta para el estado 'cancelado'.
     * Por ahora, confiamos en que el permiso 'pedidos.procesar' es suficiente para cambiar estados
     * y la lógica de cancelación se maneja dentro del método.
     */
    public function update(UpdatePedidoRequest $request, int $id)
    {
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id); // Cargar detalles para la reversión

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        $validatedData = $request->validated();
        
        // ** LÓGICA DE CANCELACIÓN (Reversión de Stock) **
        if (isset($validatedData['estado']) && $validatedData['estado'] === 'cancelado' && $pedido->estado !== 'cancelado') {
            
            // Seguridad: No permitir cancelar si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede cancelar un pedido que ya fue entregado.'], 403);
            }

            // ** Refuerzo de Seguridad para Cancelación:**
            // Como el middleware es 'pedidos.procesar' (para Admin/Editor), si se detecta un intento de CANCELAR,
            // se puede requerir el permiso más estricto ('pedidos.cancelar' - solo Admin)
            if (!auth()->user()->can('pedidos.cancelar')) {
                 return response()->json(['error' => 'No tiene permiso para cancelar pedidos.'], 403);
            }
            
            try {
                DB::beginTransaction();

                // Revertir el stock al inventario
                foreach ($pedido->detallesPedidos as $detalle) {
                    // Bloquear el inventario para la operación
                    $inventario = $detalle->producto->inventario->first(); 

                    if ($inventario) {
                        // Revertir la cantidad del detalle
                        $inventario->cantidad_existencias += $detalle->cantidad;
                        $inventario->save();
                    }
                }
                
                // Actualizar el estado del pedido
                $pedido->update(['estado' => 'cancelado']);
                DB::commit();

                $pedido->load(['user', 'detallesPedidos.producto.inventario']);
                return response()->json([
                    'message' => 'Pedido CANCELADO con éxito. Stock revertido.', 
                    'data' => new PedidoResource($pedido)
                ], 200);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Error al cancelar el pedido y revertir el stock.', 'message' => $e->getMessage()], 500);
            }
        }
        
        // ** ACTUALIZACIÓN ESTÁNDAR (Para otros estados o total) **
        try {
            // Seguridad: No permitir cambiar nada si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede modificar un pedido que ya está en estado "entregado".'], 403);
            }
            
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un pedido y revierte el inventario (DELETE /api/pedidos/{id}).
     */
    public function destroy(int $id)
    {
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        // Condición de seguridad
        if ($pedido->estado === 'entregado') {
             return response()->json(['error' => 'No se puede eliminar o cancelar un pedido ya entregado.'], 403);
        }

        try {
            DB::beginTransaction();

            // 1. Revertir el stock al inventario
            foreach ($pedido->detallesPedidos as $detalle) {
                $inventario = $detalle->producto->inventario->first();

                if ($inventario) {
                    $inventario->cantidad_existencias += $detalle->cantidad;
                    $inventario->save();
                }
            }

            // 2. Eliminar el pedido
            $pedido->delete();
            
            DB::commit();

            return response()->json(['message' => 'Pedido y detalles eliminados con éxito. Stock revertido.'], 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar el pedido y revertir el stock.', 
                'message' => $e->getMessage()
            ], 500);
        }
    }
}