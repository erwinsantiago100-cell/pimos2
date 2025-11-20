<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Rules\CheckStock; // Importar la regla de stock

/**
 * Valida los datos al crear un nuevo Pedido.
 * Asegura que el array 'detalles' esté presente y que cada detalle tenga un producto existente y una cantidad válida.
 *
 * NOTA: El campo user_id se omite en la validación ya que el controlador lo asigna
 * automáticamente usando el usuario autenticado (Auth::id()), lo cual es una mejor práctica.
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
            // El 'user_id' se elimina de la validación ya que el controlador lo asigna.
            // 'user_id' => 'required|exists:users,id', <-- ELIMINADO

            'estado' => 'nullable|string|in:pendiente,enviado,cancelado,entregado', // El controlador lo establece en 'pendiente' por defecto
            'total' => 'nullable|numeric|min:0', // El controlador lo calcula

            // Reglas para los Detalles del Pedido (Array anidado)
            'detalles' => ['required', 'array', 'min:1'],
            // Reglas para cada elemento dentro del array 'detalles'
            'detalles.*.producto_id' => ['required', 'integer', 'exists:productos,id'],
            
            // Regla de validación personalizada: verifica que haya suficiente stock.
            // Esta regla se ejecuta antes de la lógica del controlador.
            'detalles.*.cantidad' => ['required', 'integer', 'min:1', new CheckStock()],
            
            'detalles.*.precio_unitario' => 'nullable|numeric|min:0.01', 
        ];
    }

    /**
     * Mensajes de error personalizados.
     */
    public function messages(): array
    {
        return [
            'detalles.required' => 'El pedido debe contener al menos un producto en el array detalles.',
            'detalles.min' => 'El pedido debe contener al menos un producto.',
            'detalles.*.producto_id.exists' => 'El producto con el ID proporcionado no existe.',
            'detalles.*.cantidad.min' => 'La cantidad solicitada debe ser al menos 1.',
        ];
    }
}