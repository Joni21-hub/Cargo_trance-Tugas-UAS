<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\ShipmentLifecycleService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentLifecycleGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_cannot_transition_directly_from_created_to_delivered(): void
    {
        $user = User::first();
        $carrier = Carrier::first();
        $loc1 = Location::first();
        $loc2 = Location::skip(1)->first();

        $shipment = Shipment::create([
            'tracking_number' => 'CTG-TEST-0001',
            'customer_id' => $user->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $loc1->id,
            'destination_location_id' => $loc2->id,
            'status' => 'CREATED',
            'cargo_description' => 'Test Cargo',
            'weight_kg' => 1000,
            'expected_departure' => now(),
            'expected_arrival' => now()->addDays(5),
            'calculated_eta' => now()->addDays(5),
        ]);

        $service = app(ShipmentLifecycleService::class);

        $this->expectException(DomainException::class);
        $service->transition($shipment, 'DELIVERED');
    }

    public function test_valid_sequential_transitions_succeed(): void
    {
        $shipment = Shipment::where('status', 'CREATED')->first() ?? Shipment::first();
        $shipment->status = 'CREATED';
        $shipment->save();

        $service = app(ShipmentLifecycleService::class);

        $service->transition($shipment, 'BOOKED');
        $this->assertEquals('BOOKED', $shipment->fresh()->status);

        $service->transition($shipment, 'PICKED_UP');
        $this->assertEquals('PICKED_UP', $shipment->fresh()->status);

        $service->transition($shipment, 'IN_TRANSIT');
        $this->assertEquals('IN_TRANSIT', $shipment->fresh()->status);

        $service->transition($shipment, 'DELIVERED');
        $this->assertEquals('DELIVERED', $shipment->fresh()->status);
    }
}
