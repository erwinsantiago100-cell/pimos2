<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Http\Resources\InventarioResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gestiona la lógica CRUD para el Inventario (Stock).
 */
class InventarioController extends Controller
{
    /**
     * Define los middlewares de autorización basados en los permisos de Inventario.
     */
    public function __construct()
    {
        // Permisos de Lectura
        $this->middleware('can:inventario.ver')->only(['index', 'show']);
        
        // Permiso de Creación
        $this->middleware('can:inventario.crear')->only('store');
        
        // Permiso de Actualización de Stock
        $this->middleware('can:inventario.ajustar_stock')->only('update');
        
        // Permiso de Eliminación de Registro (Solo Admin)
        $this->middleware('can:inventario.eliminar_registro')->only('destroy');
    }
    
    /**
     * Muestra una lista de todos los registros de inventario (GET /api/inventario).
     */
    public function index()
    {
        $inventarios = Inventario::with('producto')->get();
        return InventarioResource::collection($inventarios);
    }
    
    /**
     * Almacena un nuevo registro de inventario (POST /api/inventario).
     * Se usa para dar stock a un producto que aún no tiene inventario.
     */
    public function store(Request $request)
    {
        // Validación: el producto debe existir y no debe tener ya un registro de inventario
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id|unique:inventarios,producto_id',
            'cantidad_existencias' => 'required|integer|min:0',
        ]);

        try {
            $inventario = Inventario::create($validated);
            $inventario->load('producto');

            return response()->json([
                'message' => 'Registro de inventario creado con éxito.', 
                'data' => new InventarioResource($inventario)
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al crear el registro de inventario.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Muestra un registro de inventario específico (GET /api/inventario/{id}).
     */
    public function show(Inventario $inventario)
    {
        $inventario->load('producto');
        return new InventarioResource($inventario);
    }

    /**
     * Actualiza la cantidad de existencias (PUT/PATCH /api/inventario/{id}).
     */
    public function update(Request $request, Inventario $inventario)
    {
        $validated = $request->validate([
            'cantidad_existencias' => 'sometimes|required|integer|min:0',
        ]);

        try {
            $inventario->update($validated);
            $inventario->load('producto');
            return response()->json(['message' => 'Inventario actualizado con éxito.', 'data' => new InventarioResource($inventario)], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al actualizar el inventario.', 'message' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Elimina un registro de inventario (DELETE /api/inventario/{id}).
     */
    public function destroy(Inventario $inventario)
    {
        try {
            $inventario->delete();
            return response()->json(['message' => 'Registro de inventario eliminado con éxito.'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el registro de inventario.', 'message' => $e->getMessage()], 500);
        }
    }
}