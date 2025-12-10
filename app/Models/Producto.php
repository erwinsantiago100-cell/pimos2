<?php
//Este modelo representa la gomita individual en tu catálogo y sus conexiones con el stock y las ventas.
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Producto extends Model
{
    use HasFactory;

    // Campos que pueden ser asignados masivamente (Mass Assigned)
    protected $fillable = [
        'nombre_gomita',
        'sabor',
        'tamano',
        'precio',
    ];

    /**
     * Relación: Un producto tiene muchas líneas de inventario (su stock actual).
     * @return HasMany
     */
    //Define que un producto puede tener muchos registros de inventario (aunque en tu diseño actual, 
    // con un solo campo cantidad_existencias,
    //  probablemente solo habrá uno). Usa la clave foránea producto_id para buscar el stock.
    public function inventario(): HasMany
    {
        return $this->hasMany(Inventario::class, 'producto_id');
    }

    /**
     * Relación: Un producto puede estar en muchas líneas de detalles de pedido.
     * @return HasMany
     */
    //Define que un producto puede aparecer en muchas líneas de detalle de pedido.
    //  Esto te permite, por ejemplo, ver el historial de ventas de una gomita específica con $producto->detallesPedidos.
    public function detallesPedidos(): HasMany
    {
        return $this->hasMany(DetallePedido::class, 'producto_id');
    }
}