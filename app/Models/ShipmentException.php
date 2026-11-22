<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShipmentException extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipment_id',
        'tracking_event_id',
        'exception_type',
        'severity',
        'impact_hours',
        'description',
        'status',
        'detected_at',
        'resolved_at',
        'resolution_notes',
    ];

    protected $casts = [
        'impact_hours' => 'integer',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }

    public function trackingEvent()
    {
        return $this->belongsTo(TrackingEvent::class);
    }
}
