<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Http\Resources\InventarioResource;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response; // Importar la clase Response para los códigos de estado HTTP

// Importar el trait AuthorizesRequests para la autorización de políticas
use Illuminate\Foundation\Auth\Access\AuthorizesRequests; 

/**
 * Gestiona la lógica CRUD para el Inventario (Stock).
 */
class InventarioController extends Controller
{
    // Usar el trait AuthorizesRequests
    use AuthorizesRequests; 

    // Se elimina el método __construct y los middlewares.
    // La autorización se maneja directamente en cada método con $this->authorize.
    
    /**
     * Muestra una lista de todos los registros de inventario (GET /api/inventario).
     */
    public function index()
    {
        // Autorización: Permiso de Lectura
        $this->authorize('inventario.ver'); 

        try {
            $inventarios = Inventario::with('producto')->get();
            return InventarioResource::collection($inventarios);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Almacena un nuevo registro de inventario (POST /api/inventario).
     */
    public function store(Request $request)
    {
        // Autorización: Permiso de Creación
        $this->authorize('inventario.crear'); 

        // Validación: el producto debe existir y no debe tener ya un registro de inventario
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id|unique:inventarios,producto_id',
            'cantidad_existencias' => 'required|integer|min:0',
        ]);

        try {
            $inventario = Inventario::create($validated);
            $inventario->load('producto');

            // Devolver respuesta 201 (Created)
            return response()->json([
                'message' => 'Registro de inventario creado con éxito.', 
                'data' => new InventarioResource($inventario)
            ], Response::HTTP_CREATED);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el registro de inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Muestra un registro de inventario específico (GET /api/inventario/{id}).
     */
    public function show(Inventario $inventario)
    {
        // Autorización: Permiso de Lectura
        $this->authorize('inventario.ver'); 

        $inventario->load('producto');
        return new InventarioResource($inventario);
    }

    /**
     * Actualiza la cantidad de existencias (PUT/PATCH /api/inventario/{id}).
     */
    public function update(Request $request, Inventario $inventario)
    {
        // Autorización: Permiso de Actualización de Stock
        $this->authorize('inventario.ajustar_stock');

        $validated = $request->validate([
            'cantidad_existencias' => 'sometimes|required|integer|min:0',
        ]);

        try {
            $inventario->update($validated);
            $inventario->load('producto');
            
            // Devolver respuesta 200 (OK)
            return response()->json(['message' => 'Inventario actualizado con éxito.', 'data' => new InventarioResource($inventario)], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
    
    /**
     * Elimina un registro de inventario (DELETE /api/inventario/{id}).
     */
    public function destroy(Inventario $inventario)
    {
        // Autorización: Permiso de Eliminación de Registro (Solo Admin)
        $this->authorize('inventario.eliminar_registro');
        
        try {
            $inventario->delete();
            
            // Devolver respuesta 204 (No Content)
            return response()->json(null, Response::HTTP_NO_CONTENT);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el registro de inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}