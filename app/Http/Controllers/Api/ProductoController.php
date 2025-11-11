<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use App\Http\Resources\ProductoResource; 
use App\Http\Resources\ProductoCollection; 
use App\Http\Requests\StoreProductoRequest; 
use App\Http\Requests\UpdateProductoRequest; 

/**
 * Gestiona la lógica CRUD de las Gomitas (Productos).
 */
class ProductoController extends Controller
{
    /**
     * Define los middlewares de autorización basados en los permisos de Productos.
     */
    public function __construct()
    {
        // Permisos de Lectura (Concedido a Admin, Editor, Usuario)
        $this->middleware('can:productos.ver')->only(['index', 'show']);
        
        // Permiso de Creación (Solo Admin)
        $this->middleware('can:productos.crear')->only('store');
        
        // Permiso de Edición (Admin, Editor)
        $this->middleware('can:productos.editar')->only('update');
        
        // Permiso de Eliminación (Solo Admin)
        $this->middleware('can:productos.eliminar')->only('destroy');
    }
    
    /**
     * Muestra una lista de todos los productos (GET /api/productos).
     */
    public function index()
    {
        try {
            // Carga la relación de inventario para el stock
            $productos = Producto::with('inventario')->get();
            return new ProductoCollection($productos); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener productos.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Almacena un nuevo producto (POST /api/productos).
     */
    public function store(StoreProductoRequest $request)
    {
        // La validación ocurre automáticamente
        $validatedData = $request->validated(); 

        try {
            // Creamos solo con los campos del producto
            $producto = Producto::create($request->except('cantidad_existencias'));

            // Crear el registro de inventario inicial si se envió el stock
            if (isset($validatedData['cantidad_existencias']))
            {
                $producto->inventario()->create([
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
            }
            
            $producto->load('inventario');

            return response()->json([
                'message' => 'Producto creado con éxito.', 
                'data' => new ProductoResource($producto) 
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el producto.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un producto específico (GET /api/productos/{id}).
     */
    public function show(int $id)
    {
        $producto = Producto::with('inventario')->find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        return new ProductoResource($producto); 
    }

    /**
     * Actualiza un producto existente (PUT/PATCH /api/productos/{id}).
     */
    public function update(UpdateProductoRequest $request, int $id)
    {
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        // La validación y el "sometimes" permiten actualizaciones parciales
        $validatedData = $request->validated(); 

        try {
            $producto->update($validatedData); 
            $producto->load('inventario');

            return response()->json(['message' => 'Producto actualizado con éxito.', 'data' => new ProductoResource($producto)], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Elimina un producto (DELETE /api/productos/{id}).
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
