<?php
//Este modelo mapea la tabla detalles_pedidos, 
// que es la tabla intermedia que registra qué productos y en qué cantidad se compraron en un pedido específico.
namespace App\Models;
use App\Models\Inventario;
use App\Models\Pedido;
use App\Models\Producto;
use App\Models\DetallePedido;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetallePedido extends Model
{
    use HasFactory;

    // Especifica el nombre de la tabla
    protected $table = 'detalles_pedidos';

    // Campos que pueden ser asignados masivamente
    protected $fillable = [
        'pedido_id', 
        'producto_id', 
        'cantidad', 
        'precio_unitario',
    ];

    // Relación Inversa: La línea de detalle pertenece a un pedido.
    //Define que cada línea de detalle pertenece a un único Pedido. 
    // Usa la clave foránea pedido_id para hacer esta conexión inversa.
    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }

    // Relación Inversa: La línea de detalle pertenece a un producto.
    //Define que cada línea de detalle pertenece a un único Producto. Usa la clave foránea producto_id.
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}//

//En resumen: Te permite tomar un registro de detalle y acceder directamente al pedido y al producto relacionado,
//  por ejemplo: $detalle->pedido->total o $detalle->producto->nombre_gomita.