<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalletMutation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shipment_id',
        'type',
        'amount_usd',
        'balance_after_usd',
        'reference_code',
        'description',
    ];

    protected $casts = [
        'amount_usd' => 'float',
        'balance_after_usd' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}
