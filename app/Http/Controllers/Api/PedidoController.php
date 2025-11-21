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
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 
use Illuminate\Support\Facades\Log; // Importar Facade Log para un logging más limpio

/**
 * Gestiona la lógica CRUD de los Pedidos, incluyendo la deducción y reversión de stock.
 * Utiliza transacciones y bloqueo pesimista (lockForUpdate) para garantizar la atomicidad del inventario.
 */
class PedidoController extends Controller
{
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
            // Cargar las relaciones necesarias para el resource
            $pedidos = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->paginate(10);
            return new PedidoCollection($pedidos);

        } catch (\Exception $e) {
            Log::error("Error al obtener la lista de pedidos: " . $e->getMessage());
            return response()->json([
                'error' => 'Error al obtener los pedidos.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Almacena un nuevo pedido y deduce el inventario (POST /api/pedidos).
     */
    public function store(StorePedidoRequest $request)
    {
        $this->authorize('create', Pedido::class); 

        $validatedData = $request->validated();
        $total = 0;
        
        // Determinar el ID del usuario: usar el proporcionado o el autenticado
        $userId = $validatedData['user_id'] ?? auth()->id();

        if (!$userId) {
            return response()->json(['error' => 'Se requiere un ID de usuario válido para crear el pedido.'], Response::HTTP_UNAUTHORIZED);
        }
        
        try {
            DB::beginTransaction();

            $pedido = Pedido::create([
                'user_id' => $userId, 
                // Estado por defecto 'pendiente'
                'estado' => $validatedData['estado'] ?? 'pendiente', 
                'total' => 0, // El total se recalculará y actualizará al final
            ]);

            $detalles = [];
            foreach ($validatedData['detalles'] as $detalle) {
                // 1. Bloquear el producto (y por ende su inventario) para el control de concurrencia
                $producto = Producto::lockForUpdate()->find($detalle['producto_id']);
                
                if (!$producto) {
                    DB::rollBack();
                    return response()->json(['error' => 'Producto no encontrado: ID ' . $detalle['producto_id']], Response::HTTP_NOT_FOUND);
                }

                // Usar el operador nullsafe para obtener el inventario (PHP 8.0+)
                $inventario = $producto->inventario; // Se asume que la relación existe en el modelo Producto
                $cantidadSolicitada = (int) $detalle['cantidad'];
                $precioUnitario = (float) $producto->precio; 
                $subtotal = $cantidadSolicitada * $precioUnitario;

                // 2. Verificar stock y deducir dentro de la transacción bloqueada
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
                    // Obtener stock disponible de forma segura para el mensaje de error
                    $stock = $inventario ? $inventario->cantidad_existencias : 0;
                    return response()->json([
                        'error' => 'Stock insuficiente para ' . ($producto->nombre ?? 'producto'), 
                        'disponible' => $stock
                    ], Response::HTTP_BAD_REQUEST);
                }
            }

            // 3. Crear detalles y actualizar total del pedido
            $pedido->detallesPedidos()->createMany($detalles);
            $pedido->total = $total;
            $pedido->save();

            DB::commit();

            // Cargar relaciones finales para la respuesta
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido creado y stock actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al crear el pedido: " . $e->getMessage()); 
            
            return response()->json([
                'error' => 'Error interno al crear el pedido.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Muestra un pedido específico (GET /api/pedidos/{id}).
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user', 'detallesPedidos.producto.inventario'])->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], Response::HTTP_NOT_FOUND);
        }
        
        // Autorización para ver (permite al dueño y al Admin)
        $this->authorize('view', $pedido); 

        return new PedidoResource($pedido);
    }

    /**
     * Actualiza un pedido existente (PUT/PATCH /api/pedidos/{id}).
     * Maneja la cancelación (por dueño o Admin) o la actualización de estado (solo por Admin).
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
        
        // ** LÓGICA DE AUTORIZACIÓN **
        if ($isCancellation) {
            // Si intenta cancelar, autorizar usando la política 'cancel' (permite al dueño)
            $this->authorize('cancel', $pedido);
        } else {
            // Para cualquier otra actualización, autorizar usando la política 'update' (solo Admin)
            $this->authorize('update', $pedido); 
        }

        // ** LÓGICA DE CANCELACIÓN (Reversión de Stock) **
        if ($isCancellation && $pedido->estado !== 'cancelado') {
            
            // Seguridad: No permitir cancelar si ya está entregado
            if ($pedido->estado === 'entregado') {
                return response()->json(['error' => 'No se puede cancelar un pedido que ya fue entregado.'], Response::HTTP_FORBIDDEN);
            }
            
            try {
                DB::beginTransaction();

                // Revertir el stock al inventario
                foreach ($pedido->detallesPedidos as $detalle) {
                    $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                    
                    // Usar operador nullsafe para acceder al inventario de forma segura
                    $inventario = $producto?->inventario; 

                    if ($inventario) { // Comprobación segura
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
                Log::error("Error al cancelar pedido {$pedido->id}: " . $e->getMessage());

                return response()->json([
                    'error' => 'Error al cancelar el pedido y revertir el stock.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        }
        
        // ** ACTUALIZACIÓN ESTÁNDAR (Para otros estados como 'enviado', 'entregado' por Admin) **
        
        // Seguridad: No permitir cambiar el estado si ya está entregado o cancelado (salvo la propia cancelación manejada arriba)
        if ($pedido->estado === 'entregado' || $pedido->estado === 'cancelado') {
            return response()->json(['error' => 'No se puede modificar un pedido que ya está en estado "entregado" o "cancelado".'], Response::HTTP_FORBIDDEN);
        }
        
        try {
            $pedido->update($validatedData);
            
            $pedido->load(['user', 'detallesPedidos.producto.inventario']);
            return response()->json([
                'message' => 'Pedido actualizado con éxito.', 
                'data' => new PedidoResource($pedido)
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error("Error al actualizar pedido {$pedido->id}: " . $e->getMessage());
            return response()->json([
                'error' => 'Error al actualizar el pedido.', 
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Elimina un pedido y revierte el inventario (DELETE /api/pedidos/{id}).
     * Nota: Normalmente, solo los Admins pueden eliminar, y solo pedidos no entregados/cancelados.
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
            foreach ($pedido->detallesPedidos as $detalle) {
                // Bloquear para garantizar atomicidad
                $producto = Producto::lockForUpdate()->find($detalle->producto_id);
                
                // Usar operador nullsafe para acceder al inventario de forma segura
                $inventario = $producto?->inventario; 

                if ($inventario) { // Comprobación segura
                    $inventario->cantidad_existencias += $detalle->cantidad;
                    $inventario->save();
                }
            }

            // 2. Eliminar el pedido (lo cual eliminará los detalles en cascada si está configurado en la DB)
            $pedido->delete();
            
            DB::commit();

            return response()->json(['message' => 'Pedido y detalles eliminados con éxito. Stock revertido.'], Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error al eliminar pedido {$pedido->id}: " . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al eliminar el pedido y revertir el stock.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}