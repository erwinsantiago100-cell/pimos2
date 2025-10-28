<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pedido extends Model
{
    use HasFactory;

    // MODIFICACIÓN CLAVE: Se ELIMINA 'protected $primaryKey = 'pedido_id';'
    // Ahora, Laravel usará la clave primaria por defecto, que es 'id',
    // coincidiendo con lo que existe en tu base de datos (según tu migración).

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'user_id',
        'total',
        'estado',
    ];

    // Relación Inversa: El pedido pertenece a un usuario (cliente).
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Relación: Un pedido tiene muchas líneas de detalle.
    public function detallesPedidos(): HasMany
    {
        // Esta relación sigue siendo correcta, ya que la clave foránea en
        // la tabla 'detalles_pedidos' se llama 'pedido_id'.
        return $this->hasMany(DetallePedido::class, 'pedido_id');
    }
}
