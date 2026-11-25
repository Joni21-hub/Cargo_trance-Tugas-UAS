<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    use HasFactory;

    protected $fillable = [
        'tracking_number',
        'container_number',
        'customer_id',
        'buyer_id',
        'carrier_id',
        'origin_location_id',
        'destination_location_id',
        'current_location_id',
        'status',
        'transport_mode',
        'pickup_address',
        'pickup_latitude',
        'pickup_longitude',
        'seal_number',
        'verification_checklist',
        'release_document_number',
        'release_document_type',
        'release_courier_tracking',
        'release_issued_at',
        'simulated_days_elapsed',
        'cargo_description',
        'weight_kg',
        'volume_cbm',
        'expected_departure',
        'expected_arrival',
        'calculated_eta',
        'is_delayed',
        'delay_hours',
        'notes',
    ];

    protected $casts = [
        'expected_departure' => 'datetime',
        'expected_arrival' => 'datetime',
        'calculated_eta' => 'datetime',
        'release_issued_at' => 'datetime',
        'is_delayed' => 'boolean',
        'delay_hours' => 'integer',
        'simulated_days_elapsed' => 'integer',
        'weight_kg' => 'float',
        'volume_cbm' => 'float',
        'pickup_latitude' => 'float',
        'pickup_longitude' => 'float',
        'verification_checklist' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function items()
    {
        return $this->hasMany(ShipmentItem::class);
    }

    public function carrier()
    {
        return $this->belongsTo(Carrier::class);
    }

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }

    public function destinationLocation()
    {
        return $this->belongsTo(Location::class, 'destination_location_id');
    }

    public function currentLocation()
    {
        return $this->belongsTo(Location::class, 'current_location_id');
    }

    public function trackingEvents()
    {
        return $this->hasMany(TrackingEvent::class)->orderBy('event_time', 'asc');
    }

    public function exceptions()
    {
        return $this->hasMany(ShipmentException::class)->orderBy('created_at', 'desc');
    }

    protected $appends = [
        'delay_severity',
        'travel_progress_pct',
    ];

    public function getTravelProgressPctAttribute(): int
    {
        if ($this->status === 'DELIVERED') {
            return 100;
        }
        if (in_array($this->status, ['CREATED', 'BOOKED'], true)) {
            return 8;
        }
        if ($this->status === 'CONFIRMED') {
            return 18;
        }
        if ($this->status === 'PICKED_UP') {
            return 30;
        }

        $dep = $this->expected_departure;
        $arr = $this->expected_arrival;
        if ($dep && $arr && $arr->gt($dep)) {
            $totalHours = max(1, $dep->diffInHours($arr));
            $elapsedHours = max(0, $dep->diffInHours(now(), false));
            $pct = (int) round(($elapsedHours / $totalHours) * 100);
            return max(25, min(95, $pct));
        }

        return 50;
    }

    public function getDelaySeverityAttribute(): string
    {
        $hours = (int) ($this->delay_hours ?? 0);
        if ($hours <= 0) {
            return 'NONE';
        }
        if ($hours <= 6) {
            return 'LOW';
        }
        if ($hours <= 24) {
            return 'MEDIUM';
        }
        return 'HIGH';
    }

    public function escrow()
    {
        return $this->hasOne(EscrowTransaction::class);
    }

    public function activeExceptions()
    {
        return $this->hasMany(ShipmentException::class)->whereIn('status', ['OPEN', 'INVESTIGATING', 'UNDER_INVESTIGATION']);
    }
}
