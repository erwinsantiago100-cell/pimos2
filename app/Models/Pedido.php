<?php

namespace App\Models;

//Este modelo representa una orden de compra completa y 
// maneja las relaciones con el usuario que la hizo y los productos que contiene.

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
        //Modelo Hijo (el "muchos") pertenece a un modelo padre.
        return $this->belongsTo(User::class);
    }

    // Relación: Un pedido tiene muchas líneas de detalle.
    //Define que un pedido tiene muchas líneas de detalle de pedido. Esta es la parte uno-a-muchos de la relación.
    //  Puedes cargar todos los productos de un pedido con $pedido->detallesPedidos.
    public function detallesPedidos(): HasMany
    {
        // Esta relación sigue siendo correcta, ya que la clave foránea en
        // la tabla 'detalles_pedidos' se llama 'pedido_id'.
        //hasmany (Modelo Padre (el "uno")) es el modrlo que define la relación uno a muchos, 
        // es decir el modelo padre tiene muchos modelos hijos relacionados.
        return $this->hasMany(DetallePedido::class, 'pedido_id');
    }
}
//En resumen: Es el punto central para gestionar la lógica de las transacciones:
//  saber quién compró (user) y qué compró (detallesPedidos).