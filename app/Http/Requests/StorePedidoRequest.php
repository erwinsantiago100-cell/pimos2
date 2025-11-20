<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator; // Importar la interfaz Validator 
use Illuminate\Http\Exceptions\HttpResponseException; // Importar la excepción HttpResponseException 
use Illuminate\Foundation\Http\FormRequest;
use App\Models\Inventario; // Necesario para la validación del stock

/**
 * Valida los datos al crear un nuevo Pedido.
 * Asegura que el array 'detalles' esté presente y que cada detalle tenga un producto existente y una cantidad válida.
 */
class StorePedidoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     */
    public function authorize(): bool
    {
        // La autorización se maneja a nivel de Policy (PedidoPolicy::create)
        return true; 
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     */
    public function rules(): array
    {
        return [
            // Se permite enviar 'user_id' (e.g., por un Admin) pero es opcional,
            // si no se envía, el controlador asignará el usuario autenticado.
            'user_id' => 'nullable|integer|exists:users,id', 

            'estado' => 'nullable|string|in:pendiente,enviado,cancelado,entregado', // El controlador lo establece en 'pendiente' por defecto
            'total' => 'nullable|numeric|min:0', // El controlador lo calcula

            // Reglas para los Detalles del Pedido (Array anidado)
            'detalles' => ['required', 'array', 'min:1'],
            // Reglas para cada elemento dentro del array 'detalles'
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            
            // Regla de validación de stock usando Closure (Solución al error 500)
            'detalles.*.cantidad' => [
                'required', 
                'integer', 
                'min:1', 
                
                function ($attribute, $value, $fail) {
                    // $value es la cantidad solicitada.
                    $cantidadSolicitada = (int) $value;

                    // Extraer el índice del array 'detalles'
                    $index = explode('.', $attribute)[1];
                    
                    // Obtener el producto_id correspondiente
                    $productoId = $this->input('detalles')[$index]['producto_id'] ?? null;

                    // Verificar stock
                    $inventario = Inventario::where('producto_id', $productoId)->first();
                    $stockDisponible = $inventario ? $inventario->cantidad_existencias : 0;

                    if ($cantidadSolicitada > $stockDisponible) {
                        $fail("Stock insuficiente para el producto ID $productoId. Disponible: $stockDisponible.");
                    }
                },
            ],
            
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0.01', 
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'user_id.exists' => 'El ID de usuario proporcionado no existe.',
            'detalles.required' => 'El pedido debe contener al menos un producto en el array detalles.',
            'detalles.min' => 'El pedido debe contener al menos un producto.',
            'detalles.*.producto_id.exists' => 'El producto con el ID proporcionado no existe.',
            'detalles.*.cantidad.min' => 'La cantidad solicitada debe ser al menos 1.',
            // El mensaje de stock es manejado por el closure
        ];
    }

    /**
     * Manejar la falla de validación y devolver una respuesta JSON personalizada (422).
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'message' => 'Error de validación al crear un pedido',
            'errors' => $validator->errors()
        ], 422));
    }
}