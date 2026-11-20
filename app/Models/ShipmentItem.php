<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'item_name',
        'category',
        'quantity',
        'weight_kg',
        'condition',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'weight_kg' => 'float',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
