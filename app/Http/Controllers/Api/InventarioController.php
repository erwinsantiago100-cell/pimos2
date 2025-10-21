<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona la visualización y actualización del Inventario.
 */
class InventarioController extends Controller
{
    /**
     * Muestra la lista completa del inventario (stock de todos los productos).
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Carga el inventario, incluyendo los datos del producto al que pertenece
            $inventario = Inventario::with('producto:id,nombre_gomita,precio')->get();
            return response()->json($inventario, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el inventario.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra el stock de un producto específico.
     *
     * Nota: La clave primaria de Inventario es 'inventario_id', pero aquí es más útil buscar por 'producto_id'.
     *
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $producto_id)
    {
        $inventario = Inventario::where('producto_id', $producto_id)
                                ->with('producto:id,nombre_gomita,precio')
                                ->first();

        if (!$inventario) {
            return response()->json(['error' => 'Stock no encontrado para ese producto.'], 404);
        }

        return response()->json($inventario, 200);
    }

    /**
     * Actualiza la cantidad de existencias para un producto específico (usando su ID).
     * @param \Illuminate\Http\Request $request
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $producto_id)
    {
        // 1. Validar la solicitud
        try {
            $validatedData = $request->validate([
                'cantidad_existencias' => 'required|integer|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        }

        // 2. Buscar el registro de Inventario por producto_id
        $inventario = Inventario::where('producto_id', $producto_id)->first();

        if (!$inventario) {
            // Si el producto existe pero no tiene inventario, creamos el registro
            if (Producto::find($producto_id)) {
                 $inventario = Inventario::create([
                    'producto_id' => $producto_id,
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
                return response()->json(['message' => 'Inventario inicial creado con éxito.', 'data' => $inventario], 201);
            }
            return response()->json(['error' => 'Producto no encontrado o no tiene registro de inventario.'], 404);
        }

        // 3. Actualizar la cantidad
        $inventario->update($validatedData);

        return response()->json(['message' => 'Stock actualizado con éxito.', 'data' => $inventario], 200);
    }
}
