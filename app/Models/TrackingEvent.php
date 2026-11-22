<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TrackingEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'location_id',
        'latitude',
        'longitude',
        'event_type',
        'event_time',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'event_time' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function location()
    {
        return $this->belongsTo(Location::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function exceptions()
    {
        return $this->hasMany(ShipmentException::class);
    }
}
