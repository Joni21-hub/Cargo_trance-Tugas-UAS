<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PickupRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_code',
        'shipper_id',
        'buyer_id',
        'branch_location_id',
        'destination_location_id',
        'pickup_city',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'target_days',
        'preferred_mode',
        'agreed_escrow_usd',
        'commodity_summary',
        'scheduled_note',
        'status',
        'assigned_operator_id',
        'shipment_id',
    ];

    protected $casts = [
        'pickup_latitude' => 'float',
        'pickup_longitude' => 'float',
        'target_days' => 'integer',
        'agreed_escrow_usd' => 'float',
    ];

    public function shipper()
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function branchLocation()
    {
        return $this->belongsTo(Location::class, 'branch_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function assignedOperator()
    {
        return $this->belongsTo(User::class, 'assigned_operator_id');
    }

    public function shipment()
    {
        return $this->belongsTo(Shipment::class, 'shipment_id');
    }
}
