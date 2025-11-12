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
use Symfony\Component\HttpFoundation\Response; // Importar la clase Response para los códigos de estado HTTP

// Importar el trait AuthorizesRequests para la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * Gestiona la lógica CRUD de los Pedidos.
 */
class PedidoController extends Controller
{
    // Usar el trait AuthorizesRequests
    use AuthorizesRequests; 

    // Se elimina el método __construct y los middlewares.
    // La autorización se maneja directamente en cada método con $this->authorize.
    
    /**
     * Muestra una lista de todos los pedidos (GET /api/pedidos).
     */
    public function index()
    {
        // Permiso de Lectura (pedidos.ver)
        $this->authorize('pedidos.ver'); 

        try {
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener los pedidos.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Almacena un nuevo pedido y deduce el inventario (POST /api/pedidos).
     */
    public function store(StorePedidoRequest $request)
    {
        // Permiso de Creación (pedidos.crear)
        $this->authorize('pedidos.crear'); 

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
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
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
                    ], Response::HTTP_BAD_REQUEST);
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
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al crear el pedido.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Muestra un pedido específico (GET /api/pedidos/{id}).
     */
    public function show(int $id)
    {
        // Permiso de Lectura (pedidos.ver)
        $this->authorize('pedidos.ver'); 

        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * Permite cambiar el estado (procesar) o cancelar (requiere permiso estricto).
     */
    public function update(UpdatePedidoRequest $request, int $id)
    {
        // Permiso base para cambiar estados (pedidos.procesar)
        $this->authorize('pedidos.procesar'); 

        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id); // Cargar detalles para la reversión

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validated();
        
        // ** LÓGICA DE CANCELACIÓN (Reversión de Stock) **
        if (isset($validatedData['estado']) && $validatedData['estado'] === 'cancelado' && $pedido->estado !== 'cancelado') {
            
            // Seguridad: No permitir cancelar si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede cancelar un pedido que ya fue entregado.'], Response::HTTP_FORBIDDEN);
            }

            // ** Autorización ESTRICTA para Cancelación:**
            // Usamos authorize para verificar que el usuario tenga el permiso más alto ('pedidos.cancelar')
            // Si falla, lanza 403 automáticamente.
            $this->authorize('pedidos.cancelar');
            
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
                ], Response::HTTP_OK);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['error' => 'Error al cancelar el pedido y revertir el stock.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        // ** ACTUALIZACIÓN ESTÁNDAR (Para otros estados o total) **
        try {
            // Seguridad: No permitir cambiar nada si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede modificar un pedido que ya está en estado "entregado".'], Response::HTTP_FORBIDDEN);
            }
            
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el pedido.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina un pedido y revierte el inventario (DELETE /api/pedidos/{id}).
     */
    public function destroy(int $id)
    {
        // Permiso de Cancelación/Eliminación (pedidos.cancelar)
        $this->authorize('pedidos.cancelar');

        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Condición de seguridad
        if ($pedido->estado === 'entregado') {
             return response()->json(['error' => 'No se puede eliminar o cancelar un pedido ya entregado.'], Response::HTTP_FORBIDDEN);
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

            return response()->json(['message' => 'Pedido y detalles eliminados con éxito. Stock revertido.'], Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Error al eliminar el pedido y revertir el stock.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}