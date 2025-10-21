<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Gestiona la lógica CRUD de las Gomitas (Productos).
 */
class ProductoController extends Controller
{
    /**
     * Muestra una lista de todos los productos.
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Carga todos los productos y su relación de inventario
            $productos = Producto::with('inventario')->get();
            return response()->json($productos, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener la lista de productos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Almacena un nuevo producto en la base de datos.
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'nombre_gomita' => 'required|string|max:255|unique:productos,nombre_gomita',
                'sabor' => 'required|string|max:255',
                'tamano' => 'required|string|max:255',
                'precio' => 'required|numeric|min:0.01',
                'cantidad_existencias' => 'nullable|integer|min:0', // Opcional para crear el inventario inicial
            ]);

            // 1. Crear el Producto
            $producto = Producto::create($validatedData);

            // 2. Crear el registro de Inventario inicial (si se proporcionó la cantidad)
            if (isset($validatedData['cantidad_existencias'])) {
                $producto->inventario()->create([
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
            }

            return response()->json(['message' => 'Producto creado con éxito.', 'data' => $producto->load('inventario')], 201);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el producto.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un producto específico.
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $id)
    {
        $producto = Producto::with('inventario')->find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        return response()->json($producto, 200);
    }

    /**
     * Actualiza un producto existente.
     * @param \Illuminate\Http\Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        try {
            $validatedData = $request->validate([
                // Permite cambiar el nombre, pero verifica si es único si se cambia
                'nombre_gomita' => 'sometimes|required|string|max:255|unique:productos,nombre_gomita,' . $id,
                'sabor' => 'sometimes|required|string|max:255',
                'tamano' => 'sometimes|required|string|max:255',
                'precio' => 'sometimes|required|numeric|min:0.01',
            ]);

            $producto->update($validatedData);

            return response()->json(['message' => 'Producto actualizado con éxito.', 'data' => $producto], 200);

        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], 422);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el producto.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Elimina un producto.
     *
     * IMPORTANTE: La migración de productos tiene onDelete('cascade') en Inventario y Detalles_Pedidos.
     * Esto significa que al eliminar un producto, todos los registros relacionados en esas tablas también se eliminarán.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        try {
            $producto->delete();
            return response()->json(['message' => 'Producto eliminado con éxito.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
}
