<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use App\Services\TrackingEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingEventServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_record_event_updates_location_and_creates_audit_log(): void
    {
        $shipment = Shipment::where('status', 'IN_TRANSIT')->firstOrFail();
        $user = User::firstOrFail();
        $newLocation = Location::where('id', '!=', $shipment->current_location_id)->firstOrFail();

        $service = app(TrackingEventService::class);
        $event = $service->recordEvent($shipment, [
            'location_id' => $newLocation->id,
            'event_type' => 'CHECKPOINT',
            'remarks' => 'Vessel transited waypoint',
        ], $user->id);

        $this->assertDatabaseHas('tracking_events', [
            'id' => $event->id,
            'shipment_id' => $shipment->id,
            'location_id' => $newLocation->id,
            'event_type' => 'CHECKPOINT',
        ]);

        $shipment->refresh();
        $this->assertEquals($newLocation->id, $shipment->current_location_id);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action' => 'CREATE_TRACKING_EVENT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
        ]);
    }

    public function test_departure_event_automatically_transitions_status_to_in_transit(): void
    {
        $shipment = Shipment::firstOrFail();
        $shipment->status = 'PICKED_UP';
        $shipment->save();

        $location = Location::firstOrFail();
        $service = app(TrackingEventService::class);

        $service->recordEvent($shipment, [
            'location_id' => $location->id,
            'event_type' => 'DEPARTURE',
            'remarks' => 'Vessel departed origin port',
        ]);

        $shipment->refresh();
        $this->assertEquals('IN_TRANSIT', $shipment->status);
    }

    public function test_delivered_event_automatically_transitions_status_to_delivered(): void
    {
        $shipment = Shipment::where('status', 'IN_TRANSIT')->firstOrFail();
        $destLocation = Location::find($shipment->destination_location_id);

        $service = app(TrackingEventService::class);
        $service->recordEvent($shipment, [
            'location_id' => $destLocation->id,
            'event_type' => 'DELIVERED',
            'remarks' => 'Cargo discharged and received',
        ]);

        $shipment->refresh();
        $this->assertEquals('DELIVERED', $shipment->status);
    }
}
