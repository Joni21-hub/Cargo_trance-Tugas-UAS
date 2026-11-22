<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EscrowTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'shipper_id',
        'buyer_id',
        'amount_usd',
        'status',
        'deposited_at',
        'released_at',
        'notes',
    ];

    protected $casts = [
        'amount_usd' => 'float',
        'deposited_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function shipper()
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }
}
