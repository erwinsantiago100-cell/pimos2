<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventario;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Resources\InventarioResource; // Importado
use Illuminate\Support\Facades\Auth; // Importar para Autenticación
use Symfony\Component\HttpFoundation\Response; // Importar para respuestas HTTP

/**
 * @OA\Tag(
 * name="Inventario",
 * description="Operaciones de gestión de stock de productos"
 * )
 * @OA\Tag(
 * name="Autenticación",
 * description="Operaciones de inicio y cierre de sesión para obtener/revocar tokens de acceso."
 * )
 * * Gestiona la visualización y actualización del Inventario, e incluye los métodos de Autenticación.
 */
class InventarioController extends Controller
{
    /**
     * @OA\Post(
     * path="/api/login",
     * summary="Generar Token de Acceso (Login)",
     * description="Autentica al usuario con correo, contraseña y dispositivo para obtener un token de acceso (Sanctum).",
     * tags={"Autenticación"},
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"correo", "contraseña", "dispositivo"},
     * @OA\Property(property="correo", type="string", format="email", example="admin@pimos.com", description="Correo electrónico del usuario."),
     * @OA\Property(property="contraseña", type="string", format="password", example="password", description="Contraseña del usuario."),
     * @OA\Property(property="dispositivo", type="string", example="windows", description="Nombre del dispositivo (Sanctum).")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Inicio de sesión exitoso. Retorna un token de acceso.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Inicio de sesión exitoso."),
     * @OA\Property(property="token", type="string", example="lkYTYtksh64dJh9bqKrFa5GHpQZax2IA9D8fdI7Rc54a30ac", description="Token de acceso (Bearer Token).")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="Credenciales no válidas.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Credenciales inválidas.")
     * )
     * )
     * )
     */
    public function login(Request $request)
    {
        //1. Validación: Recibe correo, contraseña y dispositivo.
        //  2. Autenticación: Usa Auth::attempt() para verificar las credenciales,
        //  mapeando los campos en español a los de la base de datos (email y password).
    
        // para crear un nuevo token de acceso (Bearer Token) para el dispositivo.
      
        // Validación usando los campos en español
        $request->validate([
            'correo' => 'required|email',
            'contraseña' => 'required',
            'dispositivo' => 'required|string',
        ]);

        // Intento de autenticación: mapear 'correo' a 'email' y 'contraseña' a 'password'
        if (!Auth::attempt([
            'email' => $request->correo,
            'password' => $request->contraseña,
        ])) {
            throw ValidationException::withMessages([
                'correo' => ['Credenciales inválidas.'],
            ]);
        }
//  3. Generación de Token: Si es exitoso, 
        // usa $user->createToken($request->dispositivo)->plainTextToken 
        $user = $request->user();
        
        $token = $user->createToken($request->dispositivo)->plainTextToken;

        return response()->json([
            'message' => 'Inicio de sesión exitoso.',
            'token' => $token
        ], Response::HTTP_OK);
    }

    /**
     * @OA\Post(
     * path="/api/logout",
     * summary="Cerrar sesión y revocar Token",
     * description="Invalida el token de acceso actual del usuario autenticado.",
     * tags={"Autenticación"},
     * security={{"bearer_token":{}}},
     * @OA\Response(
     * response=200,
     * description="Cierre de sesión exitoso. Token revocado.",
     * @OA\JsonContent(
     * @OA\Property(property="message", type="string", example="Cierre de sesión exitoso. Token revocado.")
     * )
     * ),
     * @OA\Response(
     * response=401,
     * description="No autenticado.",
     * )
     * )
     */
    public function logout(Request $request)
    {
        // Revoca el token de acceso actual del usuario
        //1. Revocación: Usa $request->user()->currentAccessToken()->delete()
        //  para eliminar el token actual utilizado para hacer la petición.
        //  Esto invalida inmediatamente la sesión del usuario en ese dispositivo.
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Cierre de sesión exitoso. Token revocado.'
        ], Response::HTTP_OK);
    }
    
    /**
     * Muestra la lista completa del inventario (stock de todos los productos).
     * @OA\Get(
     * path="/api/inventario",
     * summary="Consultar todo el inventario",
     * description="Retorna una lista de todos los registros de inventario con los productos asociados.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(type="array", @OA\Items(ref="#/components/schemas/InventarioResource"))
     * ),
     * @OA\Response(response=401, description="No autenticado"),
     * @OA\Response(response=500, description="Error interno del servidor")
     * )
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        try {
            // Carga la relación 'producto'
            //Obtiene todos los registros de inventario. Utiliza Inventario::with('producto')->get() para cargar la relación del producto 
            // (evitando el problema N+1) 
            $inventario = Inventario::with('producto')->get();
            //y luego formatea la salida con InventarioResource::collection().
            // Usa el método collection() en el Resource
            return InventarioResource::collection($inventario); 

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al obtener el inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Muestra el stock de un producto específico.
     * @OA\Get(
     * path="/api/inventario/{producto_id}",
     * summary="Consultar stock de un producto",
     * description="Retorna el registro de inventario (stock) de un producto específico por su ID.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="producto_id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID del producto para consultar su inventario."
     * ),
     * @OA\Response(
     * response=200,
     * description="Operación exitosa",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(response=401, description="No autenticado"),
     * @OA\Response(response=404, description="Registro no encontrado")
     * )
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(int $producto_id)
    {

        //Busca el registro de inventario por el producto_id (Inventario::where('producto_id', $producto_id)->first()). 
        // Si existe, retorna el stock del producto con el InventarioResource
        //
        $inventario = Inventario::where('producto_id', $producto_id)
                                ->with('producto') // Carga la relación
                                ->first();

        if (!$inventario) {
            return response()->json(['error' => 'Stock no encontrado para ese producto.'], Response::HTTP_NOT_FOUND);
        }

        return new InventarioResource($inventario); // Usa el Resource
    }

    /**
     * Actualiza la cantidad de existencias para un producto específico (o la crea si no existe).
     * @OA\Put(
     * path="/api/inventario/{producto_id}",
     * summary="Actualizar o Crear Stock",
     * description="Actualiza la cantidad de existencias para un producto o crea el registro de inventario si no existe.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="producto_id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID del producto a actualizar o crear stock."
     * ),
     * @OA\RequestBody(
     * required=true,
     * @OA\JsonContent(
     * required={"cantidad_existencias"},
     * @OA\Property(property="cantidad_existencias", type="integer", example=100, description="Nueva cantidad de existencias.")
     * )
     * ),
     * @OA\Response(
     * response=200,
     * description="Stock actualizado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(
     * response=201,
     * description="Inventario inicial creado con éxito.",
     * @OA\JsonContent(ref="#/components/schemas/InventarioResource")
     * ),
     * @OA\Response(response=401, description="No autenticado"),
     * @OA\Response(response=404, description="Producto asociado no encontrado"),
     * @OA\Response(response=422, description="Error de validación")
     * )
     * @param \Illuminate\Http\Request $request
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, int $producto_id)
    {
        //Lógica de upsert (Update/Insert): 1. Valida cantidad_existencias.
        // 2. Intenta buscar el registro existente por producto_id.
        //  3. Si existe: Lo actualiza ($inventario->update(...)).
        
        //
        try {
            $validatedData = $request->validate([
                'cantidad_existencias' => 'required|integer|min:0',
            ]);
        } catch (ValidationException $e) {
            return response()->json(['error' => 'Datos de entrada inválidos.', 'messages' => $e->errors()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $inventario = Inventario::where('producto_id', $producto_id)->first();
        $isNew = false;

        if (!$inventario) {
            // Si no existe, verificamos que el Producto exista para crear el Inventario
            //  4. Si NO existe: Verifica que el producto (Producto::find($producto_id)) 
        // exista y luego crea el registro de inventario, retornando un código 201 Created.
            if (!Producto::find($producto_id)) {
                return response()->json(['error' => 'El producto asociado no existe.'], Response::HTTP_NOT_FOUND);
            }

            // Crear el registro de inventario (código 201)
            $inventario = Inventario::create([
                'producto_id' => $producto_id,
                'cantidad_existencias' => $validatedData['cantidad_existencias']
            ]);
            $isNew = true;
        } else {
            // Actualizar el registro de inventario (código 200)
            $inventario->update($validatedData);
        }

        // Carga la relación 'producto' antes de devolver el Resource
        $inventario->load('producto');

        $statusCode = $isNew ? Response::HTTP_CREATED : Response::HTTP_OK;
        $message = $isNew ? 'Inventario inicial creado con éxito.' : 'Stock actualizado con éxito.';

        return response()->json([
            'message' => $message, 
            'data' => new InventarioResource($inventario) // Usa el Resource
        ], $statusCode);
    }
    
    /**
     * Elimina el registro de inventario para un producto específico.
     * @OA\Delete(
     * path="/api/inventario/{producto_id}",
     * summary="Eliminar registro de inventario",
     * description="Elimina el registro de stock (inventario) para un producto específico por su ID.",
     * tags={"Inventario"},
     * security={{"bearer_token":{}}},
     * @OA\Parameter(
     * name="producto_id",
     * in="path",
     * required=true,
     * @OA\Schema(type="integer", example=1),
     * description="ID del producto cuyo registro de inventario se desea eliminar."
     * ),
     * @OA\Response(response=200, description="Registro de inventario eliminado con éxito."),
     * @OA\Response(response=401, description="No autenticado"),
     * @OA\Response(response=404, description="Registro no encontrado")
     * )
     * @param int $producto_id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(int $producto_id)
    {
        //Busca y elimina el registro de inventario asociado a un producto_id.
        //  Retorna 200 OK si es exitoso o 404 Not Found si el registro de stock no existía.
        $inventario = Inventario::where('producto_id', $producto_id)->first();

        if (!$inventario) {
            return response()->json(['error' => 'Registro de inventario no encontrado para el producto.'], Response::HTTP_NOT_FOUND);
        }

        try {
            $inventario->delete();
            
            // Retorna una respuesta simple 200 OK
            return response()->json(['message' => 'Registro de inventario eliminado con éxito.'], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al eliminar el registro de inventario.', 'message' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}