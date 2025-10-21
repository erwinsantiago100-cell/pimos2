<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Pedido;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona la lógica de Pedidos, incluyendo la creación de detalles.
 */
class PedidoController extends Controller
{
    /**
     * Muestra una lista de todos los pedidos.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Carga los pedidos, incluyendo el usuario y los detalles
            $pedidos = Pedido::with(['user:id,name,email', 'detallesPedidos.producto:id,nombre_gomita'])
                                ->latest() // Muestra los más recientes primero
                                ->paginate(20); // Paginación para grandes volúmenes

            return response()->json($pedidos, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la lista de pedidos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Crea un nuevo pedido con sus líneas de detalle.
     *
     * El cuerpo de la solicitud (request body) debe incluir:
     * {
     * "user_id": 1,
     * "detalles": [
     * { "producto_id": 1, "cantidad": 5 },
     * { "producto_id": 2, "cantidad": 10 }
     * ]
     * }
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        // 1. Validar la estructura del pedido
        try {
            $validatedData = $request->validate([
                'user_id' => 'required|exists:users,id',
                'detalles' => 'required|array|min:1',
                'detalles.*.producto_id' => 'required|exists:productos,id',
                'detalles.*.cantidad' => 'required|integer|min:1',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos para el pedido.', 'messages' => $e->errors()], 422);
        }

        // Usamos una transacción para asegurar que si falla el detalle, todo el pedido se revierta.
        DB::beginTransaction();

        try {
            $totalPedido = 0;
            $detallesParaCrear = [];

            // 2. Procesar cada detalle para calcular precios y total
            foreach ($validatedData['detalles'] as $detalle) {
                $producto = Producto::find($detalle['producto_id']);
                
                // Nota: Aquí deberías verificar si hay suficiente stock en Inventario antes de crear el pedido.
                // Por simplicidad, se omite esa verificación, pero es crucial en producción.

                $precioUnitario = $producto->precio;
                $subtotal = $detalle['cantidad'] * $precioUnitario;
                $totalPedido += $subtotal;

                $detallesParaCrear[] = [
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $precioUnitario,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // 3. Crear el Pedido maestro
            $pedido = Pedido::create([
                'user_id' => $validatedData['user_id'],
                'total' => $totalPedido,
                'estado' => 'pendiente',
            ]);

            // 4. Crear los Detalles del Pedido
            $pedido->detallesPedidos()->insert($detallesParaCrear);
            
            DB::commit();

            return response()->json(['message' => 'Pedido creado con éxito.', 'data' => $pedido->load('detallesPedidos')], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => 'Error al procesar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un pedido específico.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $pedido = Pedido::with(['user:id,name,email', 'detallesPedidos.producto:id,nombre_gomita,sabor,tamano'])
                        ->find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        return response()->json($pedido, 200);
    }

    /**
     * Actualiza el estado de un pedido (típicamente usado por el administrador).
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }
        
        try {
            $validatedData = $request->validate([
                'estado' => 'sometimes|required|in:pendiente,procesando,enviado,entregado',
            ]);

            $pedido->update($validatedData);

            return response()->json(['message' => 'Estado del pedido actualizado.', 'data' => $pedido], 200);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el estado del pedido.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un pedido.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $pedido = Pedido::find($id);

        if (!$pedido) {
            return response()->json(['error' => 'Pedido no encontrado.'], 404);
        }

        try {
            $pedido->delete();
            return response()->json(['message' => 'Pedido eliminado con éxito.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el pedido.', 'message' => $e->getMessage()], 500);
        }
    }
}
