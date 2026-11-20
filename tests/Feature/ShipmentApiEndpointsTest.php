<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShipmentApiEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $operator;
    protected User $customer;
    protected Carrier $carrier;
    protected Location $origin;
    protected Location $destination;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();

        $this->admin = User::where('role', 'admin')->firstOrFail();
        $this->operator = User::where('role', 'operator')->firstOrFail();
        $this->customer = User::where('role', 'customer')->firstOrFail();
        $this->carrier = Carrier::firstOrFail();
        $this->origin = Location::where('code', 'IDBLW')->firstOrFail();
        $this->destination = Location::where('code', 'NLRTM')->firstOrFail();
    }

    /**
     * Test GET /api/v1/shipments with filtering and search.
     */
    public function test_get_shipments_list_and_search_by_container_number(): void
    {
        // Update first shipment with an ISO 6346 container number
        $shipment = Shipment::first();
        $shipment->update(['container_number' => 'CSQU3054383']);

        $response = $this->getJson('/api/v1/shipments?container_number=CSQU3054383');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 1,
                ]
            ]);
    }

    /**
     * Test GET /api/v1/shipments/{tracking_number} by container number.
     */
    public function test_get_shipment_by_container_number(): void
    {
        $shipment = Shipment::first();
        $shipment->update(['container_number' => 'MSKU9012348']);

        $response = $this->getJson('/api/v1/shipments/MSKU9012348');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $shipment->id,
                    'container_number' => 'MSKU9012348',
                ]
            ]);
    }

    /**
     * Test GET /api/v1/shipments/{id}/tracking returns checkpoints.
     */
    public function test_get_shipment_tracking_history(): void
    {
        $shipment = Shipment::has('trackingEvents')->firstOrFail();

        $response = $this->getJson("/api/v1/shipments/{$shipment->id}/tracking");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => ['id', 'shipment_id', 'location_id', 'event_type', 'event_time']
                ]
            ]);
    }

    /**
     * Test POST /api/v1/shipments with valid ISO 6346 container number succeeds.
     */
    public function test_create_shipment_with_valid_iso6346_container_number(): void
    {
        $payload = [
            'tracking_number' => 'CTG-TEST-VALID-001',
            'container_number' => 'CSQU3054383',
            'customer_id' => $this->customer->id,
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'cargo_description' => 'Test Coffee Container with valid ISO 6346',
            'weight_kg' => 18500,
            'volume_cbm' => 45,
            'expected_departure' => Carbon::now()->addDay()->toDateTimeString(),
            'expected_arrival' => Carbon::now()->addDays(15)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/shipments', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => 'CTG-TEST-VALID-001',
                    'container_number' => 'CSQU3054383',
                    'status' => 'CREATED',
                ]
            ]);

        $this->assertDatabaseHas('shipments', [
            'tracking_number' => 'CTG-TEST-VALID-001',
            'container_number' => 'CSQU3054383',
        ]);
    }

    /**
     * Test POST /api/v1/shipments with invalid check digit fails with 422.
     */
    public function test_create_shipment_with_invalid_iso6346_check_digit_fails(): void
    {
        $payload = [
            'tracking_number' => 'CTG-TEST-INVALID-001',
            'container_number' => 'CSQU3054384', // Invalid check digit (should be 3)
            'customer_id' => $this->customer->id,
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'cargo_description' => 'Test with bad check digit',
            'weight_kg' => 18500,
            'expected_departure' => Carbon::now()->addDay()->toDateTimeString(),
            'expected_arrival' => Carbon::now()->addDays(15)->toDateTimeString(),
        ];

        $response = $this->postJson('/api/v1/shipments', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Validation failed',
            ])
            ->assertJsonValidationErrors(['container_number']);
    }

    /**
     * Test PUT /api/v1/shipments/{id} RBAC and update behavior.
     */
    public function test_rbac_for_update_shipment(): void
    {
        $shipment = Shipment::first();

        // 1. Guest -> 401
        $guestResponse = $this->putJson("/api/v1/shipments/{$shipment->id}", [
            'notes' => 'Updated by guest',
        ]);
        $guestResponse->assertStatus(401);

        // 2. Customer -> 403 Forbidden
        $customerResponse = $this->actingAs($this->customer)
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'notes' => 'Updated by customer',
            ]);
        $customerResponse->assertStatus(403);

        // 3. Operator -> 200 OK
        $operatorResponse = $this->actingAs($this->operator)
            ->putJson("/api/v1/shipments/{$shipment->id}", [
                'container_number' => 'MSKU9012348',
                'notes' => 'Updated by operator with verified container',
            ]);
        $operatorResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'id' => $shipment->id,
                    'container_number' => 'MSKU9012348',
                ]
            ]);

        $this->assertEquals('MSKU9012348', $shipment->fresh()->container_number);
    }

    /**
     * Test DELETE /api/v1/shipments/{id} RBAC and deletion.
     */
    public function test_rbac_for_delete_shipment(): void
    {
        $shipment = Shipment::create([
            'tracking_number' => 'CTG-DELETE-ME',
            'customer_id' => $this->customer->id,
            'carrier_id' => $this->carrier->id,
            'origin_location_id' => $this->origin->id,
            'destination_location_id' => $this->destination->id,
            'cargo_description' => 'Temporary shipment',
            'weight_kg' => 1000,
            'expected_departure' => Carbon::now(),
            'expected_arrival' => Carbon::now()->addDays(5),
            'calculated_eta' => Carbon::now()->addDays(5),
        ]);

        // Customer -> 403
        $customerResponse = $this->actingAs($this->customer)
            ->deleteJson("/api/v1/shipments/{$shipment->id}");
        $customerResponse->assertStatus(403);

        // Operator -> 200
        $operatorResponse = $this->actingAs($this->operator)
            ->deleteJson("/api/v1/shipments/{$shipment->id}");
        $operatorResponse->assertStatus(200);

        $this->assertDatabaseMissing('shipments', ['id' => $shipment->id]);
    }

    /**
     * Test POST /api/v1/shipments/{id}/tracking RBAC and milestone addition.
     */
    public function test_rbac_for_tracking_event_store(): void
    {
        $shipment = Shipment::first();

        $payload = [
            'location_id' => $this->origin->id,
            'event_type' => 'DEPARTURE',
            'event_time' => Carbon::now()->toDateTimeString(),
            'remarks' => 'Vessel departed terminal.',
        ];

        // Customer -> 403
        $customerResponse = $this->actingAs($this->customer)
            ->postJson("/api/v1/shipments/{$shipment->id}/tracking", $payload);
        $customerResponse->assertStatus(403);

        // Operator -> 201
        $operatorResponse = $this->actingAs($this->operator)
            ->postJson("/api/v1/shipments/{$shipment->id}/tracking", $payload);
        $operatorResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Tracking checkpoint recorded successfully',
            ]);
    }

    /**
     * Test POST /api/v1/shipments/{id}/exceptions and POST /api/v1/exceptions/{id}/resolve.
     */
    public function test_rbac_for_exception_logging_and_resolution(): void
    {
        $shipment = Shipment::first();

        $exceptionPayload = [
            'exception_type' => 'PORT_CONGESTION',
            'severity' => 'MEDIUM',
            'impact_hours' => 18,
            'description' => 'Berthing delay due to port congestion.',
        ];

        // Customer cannot log exception -> 403
        $this->actingAs($this->customer)
            ->postJson("/api/v1/shipments/{$shipment->id}/exceptions", $exceptionPayload)
            ->assertStatus(403);

        // Operator can log exception -> 201
        $opResponse = $this->actingAs($this->operator)
            ->postJson("/api/v1/shipments/{$shipment->id}/exceptions", $exceptionPayload)
            ->assertStatus(201);

        $exceptionId = $opResponse->json('data.exception.id');
        $this->assertNotNull($exceptionId);

        // Customer cannot resolve exception -> 403
        $this->actingAs($this->customer)
            ->postJson("/api/v1/exceptions/{$exceptionId}/resolve", [
                'resolution_notes' => 'Customer trying to resolve',
            ])
            ->assertStatus(403);

        // Operator can resolve exception -> 200
        $this->actingAs($this->operator)
            ->postJson("/api/v1/exceptions/{$exceptionId}/resolve", [
                'resolution_notes' => 'Berth allocated, cargo moving.',
            ])
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Exception resolved and ETA recalculated',
            ]);
    }
}
