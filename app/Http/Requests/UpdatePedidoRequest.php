<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos al actualizar un Pedido.
 * Se inyecta en el método 'update($request, $id)' del PedidoController.
 */
class UpdatePedidoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * @return bool
     */
    public function authorize(): bool
    {
        return true; 
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Usamos 'sometimes' porque el usuario puede enviar solo el 'estado' o solo el 'total'.
        // Al menos uno de los dos debe estar presente, pero eso se maneja en el controlador
        // si la petición llega vacía (aunque la validación de Laravel ya lo previene
        // si se requiere). Aquí definimos qué es válido si se envía.
        return [
            // El estado solo se valida si está presente. Debe ser uno de los valores definidos.
            'estado' => 'sometimes|required|string|in:pendiente,enviado,cancelado,entregado',
            
            // El total solo se valida si está presente. Debe ser numérico y mayor que 0.01.
            'total' => 'sometimes|required|numeric|min:0.01',

            // No se valida la modificación de detalles (productos) a través de esta ruta simple de PATCH.
            // La modificación de detalles requeriría una ruta separada o un método más complejo.
        ];
    }
}
