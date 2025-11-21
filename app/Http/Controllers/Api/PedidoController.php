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
use Symfony\Component\HttpFoundation\Response; 

// Importar el trait AuthorizesRequests para la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * Gestiona la lógica CRUD de los Pedidos, incluyendo la deducción y reversión de stock.
 * Utiliza transacciones y bloqueo pesimista (lockForUpdate) para garantizar la atomicidad del inventario.
 */
class PedidoController extends Controller
{
    // Usar el trait AuthorizesRequests
    use AuthorizesRequests; 
    
    /**
     * Muestra una lista de todos los pedidos (GET /api/pedidos).
     * Solo para Administradores.
     */
    public function index()
    {
        // Autorización para ver la lista completa de pedidos (usa viewAny de PedidoPolicy)
        $this->authorize('viewAny', Pedido::class); 

        try {
            // Cargar las relaciones necesarias para el recurso
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al obtener los pedidos.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Almacena un nuevo pedido y deduce el inventario (POST /api/pedidos).
     * Permitido para cualquier usuario autenticado.
     */
    public function store(StorePedidoRequest $request)
    {
        // Autorización para crear un pedido (usa create de PedidoPolicy)
        $this->authorize('create', Pedido::class); 

        $validatedData = $request->validated();
        $total = 0;
        
        // 1. Determinar el user_id: usar el ID validado si se envió (e.g., por un Admin), sino, el autenticado.
        $userId = $validatedData['user_id'] ?? auth()->id();

        if (!$userId) {
            // Esto solo ocurriría si el usuario no está autenticado y no se pasa user_id (caso de error en la capa de autenticación).
            return response()->json(['error' => 'Se requiere un ID de usuario válido para crear el pedido.'], Response::HTTP_UNAUTHORIZED);
        }
        
        try {
            DB::beginTransaction();

            $pedido = Pedido::create([
                'user_id' => $userId, 
                // Asignar estado por defecto si no se proporciona (o usar el validado si existe)
                'estado' => $validatedData['estado'] ?? 'pendiente', 
                'total' => 0,
            ]);

            $detalles = [];
            foreach ($validatedData['detalles'] as $detalle) {
                // Bloquear el producto para asegurar la atomicidad del inventario antes de la deducción
                $producto = Producto::lockForUpdate()->find($detalle['producto_id']);
                
                if (!$producto) {
                    DB::rollBack();
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
                }

                // Usamos optional chaining para acceder a inventario de forma segura (aunque debería existir)
                $inventario = $producto->inventario->first();
                $cantidadSolicitada = (int) $detalle['cantidad'];
                $precioUnitario = (float) $producto->precio; // Usamos el precio del producto al momento de la compra
                $subtotal = $cantidadSolicitada * $precioUnitario;

                // Deducir Inventario y verificar stock de nuevo dentro de la transacción bloqueada
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
                        'error' => 'Stock insuficiente para ' . ($producto->nombre ?? 'producto'), 
                        'disponible' => $stock
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // Actualizar detalles y total del pedido
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
            return response()->json([
                'error' => 'Error al crear el pedido.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Muestra un pedido específico (GET /api/pedidos/{id}).
     * Permitido al dueño y al Administrador.
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        // Autorización para ver este pedido específico (usa view de PedidoPolicy)
        $this->authorize('view', $pedido); 

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * La cancelación (reversión de stock) usa la política 'cancel'.
     * Otros cambios de estado usan la política 'update' (solo Admin).
     */
    public function update(UpdatePedidoRequest $request, int $id)
    {
        // Cargar detalles y sus inventarios antes de cualquier operación
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id); 

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $validatedData = $request->validated();
        $isCancellation = isset($validatedData['estado']) && $validatedData['estado'] === 'cancelado';
        
        // ** LÓGICA DE CANCELACIÓN (Reversión de Stock) **
        if ($isCancellation && $pedido->estado !== 'cancelado') {
            
            // Usamos la política 'cancel', que solo permite al dueño o al Admin (vía before).
            $this->authorize('cancel', $pedido);
            
            // Seguridad: No permitir cancelar si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede cancelar un pedido que ya fue entregado.'], Response::HTTP_FORBIDDEN);
            }
            
            try {
                DB::beginTransaction();

                // Revertir el stock al inventario
                foreach ($pedido->detallesPedidos as $detalle) {
                    // Bloquear el producto antes de acceder a su inventario para la reversión
                    $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                    // Accedemos de forma segura a inventario (puede ser null)
                    $inventario = $producto?->inventario->first(); 

                    if ($inventario) {
                        // Revertir la cantidad del detalle
                        $inventario->cantidad_existencias += $detalle->cantidad;
                        $inventario->save();
                    }
                    // Si el inventario no se encuentra, se ignora la reversión para ese detalle,
                    // pero se asume que el pedido es consistente.
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
                return response()->json([
                    'error' => 'Error al cancelar el pedido y revertir el stock.', 
                    'message' => $e->getMessage()
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        // ** ACTUALIZACIÓN ESTÁNDAR (Para otros estados como 'enviado', 'entregado') **
        
        // Usamos la política 'update', que está restringida SOLO al Administrador.
        $this->authorize('update', $pedido);

        try {
            // Seguridad: No permitir cambiar el estado si ya está entregado o cancelado
            if ($pedido->estado === 'entregado' || $pedido->estado === 'cancelado') {
                return response()->json(['error' => 'No se puede modificar un pedido que ya está en estado "entregado" o "cancelado".'], Response::HTTP_FORBIDDEN);
            }
            
            // Solo actualizamos campos permitidos (como el 'estado').
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error al actualizar el pedido.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina un pedido y revierte el inventario (DELETE /api/pedidos/{id}).
     * Permitido al dueño y al Administrador.
     */
    public function destroy(int $id)
    {
        $pedido = Pedido::with('detallesPedidos.producto.inventario')->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }

        // Autorización para eliminar (usa delete de PedidoPolicy)
        $this->authorize('delete', $pedido);

        // Condición de seguridad: No eliminar si ya fue entregado
        if ($pedido->estado === 'entregado') {
             return response()->json(['error' => 'No se puede eliminar un pedido ya entregado.'], Response::HTTP_FORBIDDEN);
        }

        try {
            DB::beginTransaction();

            // 1. Revertir el stock al inventario
            // Se utiliza lockForUpdate para asegurar la atomicidad en la reversión.
            foreach ($pedido->detallesPedidos as $detalle) {
                 $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                 $inventario = $producto?->inventario->first();

                if ($inventario) {
                    $inventario->cantidad_existencias += $detalle->cantidad;
                    $inventario->save();
                }
            }

            // 2. Eliminar el pedido (y sus detalles por cascada o eliminación manual si fuera necesario)
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