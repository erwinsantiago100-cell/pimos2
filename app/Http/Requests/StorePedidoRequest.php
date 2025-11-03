<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida los datos al crear un nuevo Pedido.
 * Asegura que el array 'detalles' esté presente y que cada detalle tenga un producto existente y una cantidad válida.
 * * NOTA: La validación de stock (cantidad_existencias) se hace en el controlador (PedidoController) 
 * dentro de la transacción de DB para evitar condiciones de carrera.
 */
class StorePedidoRequest extends FormRequest
{
    /**
     * Determina si el usuario está autorizado a realizar esta solicitud.
     * @return bool
     */
    public function authorize(): bool
    {
        // Si usas autenticación (ej: Sanctum), aquí se verifica el acceso.
        // Por ahora, asumimos que cualquier usuario autenticado puede crear un pedido.
        return true; 
    }

    /**
     * Obtiene las reglas de validación que se aplican a la solicitud.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Campos principales del Pedido
            'user_id' => 'required|exists:users,id',
            'estado' => 'nullable|string|in:pendiente,enviado,cancelado,entregado', // El controlador lo establece en 'pendiente' por defecto
            'total' => 'nullable|numeric|min:0', // Puede ser opcional, ya que el controlador lo calcula

            // Reglas para los Detalles del Pedido (Array anidado)
            'detalles' => 'required|array|min:1',
            // Reglas para cada elemento dentro del array 'detalles'
            'detalles.*.producto_id' => 'required|integer|exists:productos,id',
            'detalles.*.cantidad' => 'required|integer|min:1',
            // El precio unitario puede ser enviado, pero el controlador usa el precio del Producto (DB) por seguridad.
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