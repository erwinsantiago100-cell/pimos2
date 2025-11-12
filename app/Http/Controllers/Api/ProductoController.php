<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Http\Resources\ProductoResource; 
use App\Http\Resources\ProductoCollection; 
use App\Http\Requests\StoreProductoRequest; 
use App\Http\Requests\UpdateProductoRequest; 
use Illuminate\Http\Request; 
use Symfony\Component\HttpFoundation\Response; // Necesario para códigos HTTP 201, 204

// Trait para usar $this->authorize('permiso')
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * Gestiona la lógica CRUD de los Productos.
 */
class ProductoController extends Controller
{
    // Usar el trait AuthorizesRequests para la autorización de políticas
    use AuthorizesRequests; 

    // Opcional: Si quieres usar middlewares de autenticación, es mejor
    // dejarlos en routes/api.php para esta API.
    
    /**
     * Muestra una lista de todos los productos (GET /api/productos).
     */
    public function index()
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.ver'
        $this->authorize('productos.ver'); 

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
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.crear'
        $this->authorize('productos.crear'); 

        $validatedData = $request->validated(); 

        try {
            // Asumiendo que el usuario ya está autenticado a través de Sanctum, 
            // no lo asociamos directamente al producto a menos que sea necesario.
            // Creamos el producto.
            $producto = Producto::create($request->except('cantidad_existencias'));

            if (isset($validatedData['cantidad_existencias']))
            {
                $producto->inventario()->create([
                    'cantidad_existencias' => $validatedData['cantidad_existencias']
                ]);
            }
            
            $producto->load('inventario');

            // Respuesta con código 201 (Created)
            return response()->json([
                'message' => 'Producto creado con éxito.', 
                'data' => new ProductoResource($producto) 
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el producto.', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Muestra un producto específico (GET /api/productos/{id}).
     */
    public function show(int $id)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.ver'
        $this->authorize('productos.ver'); 

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
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.editar'
        $this->authorize('productos.editar');

        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }

        /* * Si quieres replicar la política de tu maestro: 
         * $this->authorize('update', $producto); 
         * Requeriría que exista una ProductoPolicy que defina la lógica 'update'.
         */

        $validatedData = $request->validated(); 

        try {
            $producto->update($validatedData); 
            $producto->load('inventario');

            // Respuesta con código 200 (OK)
            return response()->json(['message' => 'Producto actualizado con éxito.', 'data' => new ProductoResource($producto)], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Elimina un producto (DELETE /api/productos/{id}).
     */
    public function destroy(int $id)
    {
        // Autorización basada en Spatie: el usuario debe tener el permiso 'productos.eliminar'
        $this->authorize('productos.eliminar');
        
        $producto = Producto::find($id);

        if (!$producto) {
            return response()->json(['error' => 'Producto no encontrado.'], 404);
        }
        
        /* * Si quieres replicar la política de tu maestro: 
         * $this->authorize('delete', $producto); 
         * Requeriría que exista una ProductoPolicy que defina la lógica 'delete'.
         */
        
        try {
            $producto->delete();
            
            // Respuesta con código 204 (No Content)
            return response()->json(null, Response::HTTP_NO_CONTENT);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el producto.', 'message' => $e->getMessage()], 500);
        }
    }
}