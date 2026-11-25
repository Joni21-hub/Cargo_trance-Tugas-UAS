<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExportProduct extends Model
{
    use HasFactory;

    protected $fillable = [
        'shipper_id',
        'origin_location_id',
        'name',
        'island',
        'region',
        'bean_type',
        'process_method',
        'altitude_mdpl',
        'tasting_notes',
        'supply_capacity',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shipper()
    {
        return $this->belongsTo(User::class, 'shipper_id');
    }

    public function originLocation()
    {
        return $this->belongsTo(Location::class, 'origin_location_id');
    }
}
