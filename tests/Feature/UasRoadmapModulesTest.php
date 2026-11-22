<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UasRoadmapModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_public_registration_cannot_escalate_to_operator_or_admin(): void
    {
        $res = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => 'try.operator@example.com',
            'password' => 'secret123',
            'role' => 'operator',
        ]);

        $res->assertStatus(201);
        $this->assertDatabaseHas('users', [
            'email' => 'try.operator@example.com',
            'role' => 'customer',
        ]);
    }

    public function test_customer_cannot_view_other_customers_shipments_on_server(): void
    {
        $otherCustomer = User::where('email', 'koperasi@cargotrace.com')->firstOrFail();
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        // Direct show returns 403 Forbidden
        $this->actingAs($otherCustomer)
            ->getJson("/api/v1/shipments/{$shipment->tracking_number}")
            ->assertStatus(403);

        // Index ignores tampered customer_id filter and returns 0 shipments for koperasi
        $list = $this->actingAs($otherCustomer)
            ->getJson("/api/v1/shipments?customer_id={$shipment->customer_id}")
            ->assertStatus(200);

        $this->assertEquals(0, $list->json('data.total'));

        // Public sanitized endpoint works for tracking lookup without exposing customer/escrow
        $pub = $this->getJson("/api/v1/public/track/{$shipment->tracking_number}")
            ->assertStatus(200);
        $this->assertArrayNotHasKey('customer', $pub->json('data'));
        $this->assertArrayNotHasKey('escrow', $pub->json('data'));
    }

    public function test_admin_can_provision_operator_with_branch_and_edit_master_records(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();
        $singapore = Location::where('code', 'SGSIN')->firstOrFail();
        $rotterdam = Location::where('code', 'NLRTM')->firstOrFail();

        $createRes = $this->actingAs($admin)->postJson('/api/v1/admin/users', [
            'name' => 'Operator Cabang Singapura',
            'email' => 'op.sg@meridian.id',
            'password' => 'password123',
            'role' => 'operator',
            'location_id' => $singapore->id,
        ])->assertStatus(201);

        $userId = $createRes->json('data.id');
        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'role' => 'operator',
            'location_id' => $singapore->id,
        ]);

        // Admin edits operator branch & name
        $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$userId}", [
            'name' => 'Operator Cabang Rotterdam',
            'location_id' => $rotterdam->id,
        ])->assertStatus(200);

        $this->assertDatabaseHas('users', [
            'id' => $userId,
            'name' => 'Operator Cabang Rotterdam',
            'location_id' => $rotterdam->id,
        ]);

        // Admin edits carrier & location
        $carrier = Carrier::firstOrFail();
        $this->actingAs($admin)->patchJson("/api/v1/admin/carriers/{$carrier->id}", [
            'name' => 'Maersk Line Global Updated',
        ])->assertStatus(200);
        $this->assertDatabaseHas('carriers', ['id' => $carrier->id, 'name' => 'Maersk Line Global Updated']);

        $this->actingAs($admin)->patchJson("/api/v1/admin/locations/{$singapore->id}", [
            'name' => 'Port of Singapore PSA Hub',
        ])->assertStatus(200);
        $this->assertDatabaseHas('locations', ['id' => $singapore->id, 'name' => 'Port of Singapore PSA Hub']);
    }

    public function test_full_escrow_wallet_exception_investigation_and_notifications_flow(): void
    {
        $operator = User::where('role', 'operator')->firstOrFail();
        $shipper = User::where('email', 'customer@cargotrace.com')->firstOrFail();
        $buyer = User::where('email', 'buyer@cargotrace.com')->firstOrFail();
        $carrier = Carrier::firstOrFail();
        $origin = Location::where('code', 'IDTPR')->firstOrFail();
        $dest = Location::where('code', 'NLRTM')->firstOrFail();

        // 1. Operator creates shipment with $25,000 Escrow contract
        $createRes = $this->actingAs($operator)->postJson('/api/v1/shipments', [
            'tracking_number' => 'MRD-ESCROW-001',
            'container_number' => 'CSQU3054383',
            'customer_id' => $shipper->id,
            'buyer_id' => $buyer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'cargo_description' => 'Specialty Arabica 500 Bags',
            'weight_kg' => 18500,
            'contract_amount_usd' => 25000,
            'expected_departure' => Carbon::now()->addDay()->toDateTimeString(),
            'expected_arrival' => Carbon::now()->addDays(15)->toDateTimeString(),
        ])->assertStatus(201);

        $shipmentId = $createRes->json('data.id');
        $this->assertDatabaseHas('escrow_transactions', [
            'shipment_id' => $shipmentId,
            'status' => 'PENDING_DEPOSIT',
            'amount_usd' => 25000,
        ]);

        // Notifications created for Shipper & Buyer
        $this->actingAs($shipper)->getJson('/api/v1/notifications')
            ->assertStatus(200)
            ->assertJsonPath('data.unread_count', 1);

        // 2. Buyer tops up & deposits into Escrow
        $this->actingAs($buyer)->postJson('/api/v1/wallet/topup', ['amount_usd' => 30000])->assertStatus(201);
        $this->actingAs($buyer)->postJson("/api/v1/shipments/{$shipmentId}/escrow/deposit")
            ->assertStatus(200)
            ->assertJsonPath('data.escrow.status', 'HELD_IN_ESCROW');

        // 3. Shipper confirms cargo
        $this->actingAs($shipper)->postJson("/api/v1/shipments/{$shipmentId}/confirm-shipper")
            ->assertStatus(200);

        // 4. Operator logs DAMAGED exception -> freezes Escrow -> investigates -> resolves
        $excRes = $this->actingAs($operator)->postJson("/api/v1/shipments/{$shipmentId}/exceptions", [
            'exception_type' => 'DAMAGED',
            'severity' => 'HIGH',
            'impact_hours' => 18,
            'description' => 'Outer pallet moisture detected.',
        ])->assertStatus(201);

        $exceptionId = $excRes->json('data.exception.id');
        $this->assertDatabaseHas('escrow_transactions', [
            'shipment_id' => $shipmentId,
            'status' => 'FROZEN_ON_EXCEPTION',
        ]);

        $this->actingAs($operator)->postJson("/api/v1/exceptions/{$exceptionId}/investigate", [
            'notes' => 'Surveyor inspecting inner GrainPro bags.',
        ])->assertStatus(200);
        $this->assertDatabaseHas('shipment_exceptions', [
            'id' => $exceptionId,
            'status' => 'INVESTIGATING',
        ]);

        $this->actingAs($operator)->postJson("/api/v1/exceptions/{$exceptionId}/resolve", [
            'resolution_notes' => 'Inner coffee bags verified dry and intact.',
        ])->assertStatus(200);
        $this->assertDatabaseHas('escrow_transactions', [
            'shipment_id' => $shipmentId,
            'status' => 'HELD_IN_ESCROW',
        ]);

        // 5. Buyer confirms receipt -> DELIVERED & Escrow automatically released to Shipper wallet!
        $this->actingAs($buyer)->postJson("/api/v1/shipments/{$shipmentId}/confirm-buyer")
            ->assertStatus(200);

        $this->assertDatabaseHas('escrow_transactions', [
            'shipment_id' => $shipmentId,
            'status' => 'RELEASED',
        ]);
        $this->assertEquals(25000.0, (float) $shipper->fresh()->wallet_balance_usd);

        // 6. Shipper withdraws $10,000 from virtual wallet
        $this->actingAs($shipper)->postJson('/api/v1/wallet/withdraw', [
            'amount_usd' => 10000,
            'bank_destination' => 'Bank Mandiri #108-00-1928',
        ])->assertStatus(201);

        $this->assertEquals(15000.0, (float) $shipper->fresh()->wallet_balance_usd);
    }

    public function test_demo_time_travel_triggers_delay_detection(): void
    {
        $operator = User::where('role', 'operator')->firstOrFail();
        $shipment = Shipment::where('tracking_number', 'CTG-20261101-0001')->firstOrFail();

        // Advance time by 320 hours (past 12-day expected arrival + 12h delay threshold)
        $this->actingAs($operator)->postJson('/api/v1/demo/time-travel', [
            'shipment_id' => $shipment->id,
            'hours' => 320,
        ])->assertStatus(200)
          ->assertJsonPath('data.shipment.is_delayed', true);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'DEMO_TIME_TRAVEL',
            'model_id' => $shipment->id,
        ]);
    }
}
