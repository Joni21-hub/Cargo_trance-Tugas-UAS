<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'type',
        'country',
        'island',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function originShipments()
    {
        return $this->hasMany(Shipment::class, 'origin_location_id');
    }

    public function destinationShipments()
    {
        return $this->hasMany(Shipment::class, 'destination_location_id');
    }

    public function currentShipments()
    {
        return $this->hasMany(Shipment::class, 'current_location_id');
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class);
    }
}
