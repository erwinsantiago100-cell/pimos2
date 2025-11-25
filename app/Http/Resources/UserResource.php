<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @OA\Schema(
 * schema="UserResource",
 * title="User Resource",
 * description="Estructura de datos básicos del Usuario.",
 * @OA\Property(property="id", type="integer", example=1, description="ID único del usuario."),
 * @OA\Property(property="tipo", type="string", example="user"),
 * @OA\Property(
 * property="atributos",
 * type="object",
 * @OA\Property(property="name", type="string", example="Juan Pérez", description="Nombre completo del usuario."),
 * @OA\Property(property="email", type="string", format="email", example="juan.perez@example.com", description="Correo electrónico del usuario."),
 * @OA\Property(property="email_verificado_en", type="string", format="date-time", example="2024-01-01T00:00:00Z", nullable=true, description="Marca de tiempo de la verificación del correo.")
 * )
 * )
 */
class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // El 'this' hace referencia a la instancia del modelo App\Models\User
        return [
            'id' => $this->id,
            'tipo' => 'user',
            'atributos' => [
                'name' => $this->name,
                'email' => $this->email,
                'email_verificado_en' => $this->email_verified_at,
            ],
            // Los usuarios no tienen relaciones cruciales en este contexto
        ];
    }
}