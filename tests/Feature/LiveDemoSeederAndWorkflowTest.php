<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use App\Services\ExceptionManagementService;
use App\Services\ShipmentLifecycleService;
use App\Services\TrackingEventService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LiveDemoSeederAndWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    /**
     * Test all required demo accounts are present with correct roles.
     */
    public function test_demo_accounts_seeded_with_rbac_roles(): void
    {
        $admin = User::where('email', 'admin@cargotrace.com')->first();
        $this->assertNotNull($admin);
        $this->assertEquals('admin', $admin->role);

        $operator = User::where('email', 'operator@cargotrace.com')->first();
        $this->assertNotNull($operator);
        $this->assertEquals('operator', $operator->role);

        $surveyor = User::where('email', 'surveyor@cargotrace.com')->first();
        $this->assertNotNull($surveyor);
        $this->assertEquals('surveyor', $surveyor->role);
        $this->assertTrue($surveyor->isSurveyor());

        $customer = User::where('email', 'customer@cargotrace.com')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('customer', $customer->role);

        $koperasi = User::where('email', 'koperasi@cargotrace.com')->first();
        $this->assertNotNull($koperasi);
        $this->assertEquals('customer', $koperasi->role);

        $buyer = User::where('email', 'buyer@cargotrace.com')->first();
        $this->assertNotNull($buyer);
        $this->assertEquals('customer', $buyer->role);

        $buyerRotterdam = User::where('email', 'buyer.rotterdam@cargotrace.com')->first();
        $this->assertNotNull($buyerRotterdam);
    }

    /**
     * Test master UN/LOCODE locations are properly seeded.
     */
    public function test_master_un_locode_locations_seeded(): void
    {
        $codes = ['IDTPR', 'SGSIN', 'NLRTM', 'DEHAM', 'CNSHA'];

        foreach ($codes as $code) {
            $location = Location::where('code', $code)->first();
            $this->assertNotNull($location, "UN/LOCODE location {$code} must exist");
            $this->assertEquals('PORT', $location->type);
            $this->assertNotNull($location->latitude);
            $this->assertNotNull($location->longitude);
        }

        // Verify specific port names
        $this->assertStringContainsString('Tanjung Priok', (string) Location::where('code', 'IDTPR')->value('name'));
        $this->assertStringContainsString('Singapore', (string) Location::where('code', 'SGSIN')->value('name'));
        $this->assertStringContainsString('Rotterdam', (string) Location::where('code', 'NLRTM')->value('name'));
        $this->assertStringContainsString('Hamburg', (string) Location::where('code', 'DEHAM')->value('name'));
        $this->assertStringContainsString('Shanghai', (string) Location::where('code', 'CNSHA')->value('name'));
    }

    /**
     * Test international carrier records are seeded.
     */
    public function test_master_international_carriers_seeded(): void
    {
        $carrierCodes = ['MAEU', 'MSCU', 'EGLV', 'ONEU', 'CMDU', 'HLCU'];

        foreach ($carrierCodes as $code) {
            $carrier = Carrier::where('code', $code)->first();
            $this->assertNotNull($carrier, "Carrier {$code} must exist");
            $this->assertTrue((bool) $carrier->is_active);
            $this->assertEquals('SEA', $carrier->transport_mode);
        }
    }

    /**
     * Test Skenario 1: Normal / On-Time (Jakarta / Tanjung Priok -> Rotterdam, IN_TRANSIT, no delay).
     */
    public function test_scenario_1_normal_on_time(): void
    {
        /** @var Shipment|null $shipment */
        $shipment = Shipment::with(['originLocation', 'destinationLocation', 'trackingEvents', 'exceptions'])
            ->where('tracking_number', 'CTG-20261101-0001')
            ->first();

        $this->assertNotNull($shipment);
        $this->assertEquals('IDTPR', $shipment->originLocation->code);
        $this->assertEquals('NLRTM', $shipment->destinationLocation->code);
        $this->assertEquals('IN_TRANSIT', $shipment->status);
        $this->assertFalse((bool) $shipment->is_delayed);
        $this->assertEquals(0, $shipment->delay_hours);
        $this->assertCount(0, $shipment->exceptions);
        $this->assertGreaterThanOrEqual(2, $shipment->trackingEvents->count());
        $this->assertStringContainsString('Escrow', (string) $shipment->notes);
    }

    /**
     * Test Skenario 2: Delayed (Customs Hold 24 jam) (Singapore -> Hamburg, 24h customs hold, flagged delayed).
     */
    public function test_scenario_2_customs_hold_delayed(): void
    {
        /** @var Shipment|null $shipment */
        $shipment = Shipment::with(['originLocation', 'destinationLocation', 'trackingEvents', 'exceptions'])
            ->where('tracking_number', 'CTG-20261102-0002')
            ->first();

        $this->assertNotNull($shipment);
        $this->assertEquals('SGSIN', $shipment->originLocation->code);
        $this->assertEquals('DEHAM', $shipment->destinationLocation->code);
        $this->assertEquals('IN_TRANSIT', $shipment->status);
        $this->assertTrue((bool) $shipment->is_delayed, 'Shipment 2 must be flagged as delayed');
        $this->assertEquals(24, $shipment->delay_hours, 'Delay hours must be 24');

        $exception = $shipment->exceptions->first();
        $this->assertNotNull($exception);
        $this->assertEquals('CUSTOMS_HOLD', $exception->exception_type);
        $this->assertEquals(24, $exception->impact_hours);
        $this->assertEquals('OPEN', $exception->status);
        $this->assertEquals('HIGH', $exception->severity);
        $this->assertStringContainsString('Customs Hold', (string) $shipment->notes);
    }

    /**
     * Test Skenario 3: Delivered (Full milestone sequence and escrow disbursement).
     */
    public function test_scenario_3_delivered_with_milestones_and_escrow(): void
    {
        /** @var Shipment|null $shipment */
        $shipment = Shipment::with(['originLocation', 'destinationLocation', 'trackingEvents', 'exceptions'])
            ->where('tracking_number', 'CTG-20261103-0003')
            ->first();

        $this->assertNotNull($shipment);
        $this->assertEquals('IDTPR', $shipment->originLocation->code);
        $this->assertEquals('NLRTM', $shipment->destinationLocation->code);
        $this->assertEquals('DELIVERED', $shipment->status);
        $this->assertFalse((bool) $shipment->is_delayed);
        $this->assertEquals(0, $shipment->delay_hours);

        // Verify full milestone progression: DEPARTURE, IN_TRANSIT_CHECKPOINT, ARRIVAL, CUSTOMS_INSPECTION, OUT_FOR_DELIVERY, DELIVERED
        $eventTypes = $shipment->trackingEvents->pluck('event_type')->toArray();
        $this->assertContains('DEPARTURE', $eventTypes);
        $this->assertContains('IN_TRANSIT_CHECKPOINT', $eventTypes);
        $this->assertContains('ARRIVAL', $eventTypes);
        $this->assertContains('CUSTOMS_INSPECTION', $eventTypes);
        $this->assertContains('OUT_FOR_DELIVERY', $eventTypes);
        $this->assertContains('DELIVERED', $eventTypes);

        $this->assertStringContainsString('Pencairan dana escrow 100% lunas', (string) $shipment->notes);
    }

    /**
     * Test End-to-End complete workflow: Cargo registration -> Escrow DP -> Transit -> Customs Hold -> Resolution -> Delivery -> Escrow Disbursement.
     */
    public function test_end_to_end_cargo_input_to_escrow_settlement_workflow(): void
    {
        $customer = User::where('role', 'customer')->firstOrFail();
        $operator = User::where('role', 'operator')->firstOrFail();
        $carrier = Carrier::where('code', 'MAEU')->firstOrFail();
        $origin = Location::where('code', 'IDTPR')->firstOrFail();
        $dest = Location::where('code', 'NLRTM')->firstOrFail();
        $transit = Location::where('code', 'SGSIN')->firstOrFail();

        // Step 1: Input Kargo Baru via API
        $createPayload = [
            'tracking_number' => 'CTG-TEST-E2E-9999',
            'customer_id' => $customer->id,
            'carrier_id' => $carrier->id,
            'origin_location_id' => $origin->id,
            'destination_location_id' => $dest->id,
            'cargo_description' => 'E2E Demo Batch - Kopi Arabika Gayo Specialty 2x 40ft Kontainer',
            'weight_kg' => 24000.00,
            'volume_cbm' => 60.00,
            'expected_departure' => Carbon::now()->addDays(1)->toDateTimeString(),
            'expected_arrival' => Carbon::now()->addDays(20)->toDateTimeString(),
            'notes' => 'Kontrak Dagang & Escrow #ESC-E2E-9999 senilai $180,000 USD. DP 30% ($54,000 USD) disetor ke operator escrow.',
        ];

        $response = $this->actingAs($operator)->postJson('/api/v1/shipments', $createPayload);
        $response->assertStatus(201);
        $shipmentId = (int) $response->json('data.id');

        /** @var Shipment $shipment */
        $shipment = Shipment::findOrFail($shipmentId);
        $this->assertEquals('CREATED', $shipment->status);
        $this->assertEquals('CTG-TEST-E2E-9999', $shipment->tracking_number);

        // Step 2: Milestone Lifecycle Guard (CREATED -> BOOKED -> PICKED_UP)
        $lifecycleService = app(ShipmentLifecycleService::class);
        $lifecycleService->transition($shipment, 'BOOKED');
        $this->assertEquals('BOOKED', $shipment->fresh()->status);

        $lifecycleService->transition($shipment, 'PICKED_UP');
        $this->assertEquals('PICKED_UP', $shipment->fresh()->status);

        // Step 3: Departure Event triggers IN_TRANSIT status automatically
        $eventService = app(TrackingEventService::class);
        $eventService->recordEvent($shipment, [
            'location_id' => $origin->id,
            'event_type' => 'DEPARTURE',
            'event_time' => Carbon::now(),
            'remarks' => 'Kapal bertolak dari Dermaga Tanjung Priok.',
        ], (int) $operator->id);

        $shipment->refresh();
        $this->assertEquals('IN_TRANSIT', $shipment->status);

        // Step 4: Arrive at transit hub & Customs Hold Exception (24 Hours)
        $customsEvent = $eventService->recordEvent($shipment, [
            'location_id' => $transit->id,
            'event_type' => 'CUSTOMS_INSPECTION',
            'event_time' => Carbon::now()->addDays(2),
            'remarks' => 'Pemeriksaan kepabeanan & fumigasi karantina di transit Singapore.',
        ], (int) $operator->id);

        $exceptionService = app(ExceptionManagementService::class);
        $exception = $exceptionService->recordException($shipment, [
            'tracking_event_id' => $customsEvent->id,
            'exception_type' => 'CUSTOMS_HOLD',
            'severity' => 'HIGH',
            'impact_hours' => 24,
            'description' => 'Customs Hold 24 Jam: Verifikasi sertifikat phytosanitary ekspor.',
        ], (int) $operator->id);

        $shipment->refresh();
        $this->assertTrue((bool) $shipment->is_delayed, 'Must be flagged delayed after 24h exception');
        $this->assertEquals(24, $shipment->delay_hours);

        // Step 5: Resolve Customs Hold
        $exceptionService->resolveException($exception, 'Karantina cleared dan phytosanitary dinyatakan valid 100%.', (int) $operator->id);
        $shipment->refresh();
        $this->assertFalse((bool) $shipment->is_delayed, 'Delay flag must clear when resolved');
        $this->assertEquals(0, $shipment->delay_hours);

        // Step 6: Arrival and Final Delivery
        $eventService->recordEvent($shipment, [
            'location_id' => $dest->id,
            'event_type' => 'ARRIVAL',
            'event_time' => Carbon::now()->addDays(18),
            'remarks' => 'Kapal tiba di Rotterdam.',
        ], (int) $operator->id);

        $eventService->recordEvent($shipment, [
            'location_id' => $dest->id,
            'event_type' => 'DELIVERED',
            'event_time' => Carbon::now()->addDays(19),
            'remarks' => 'Serah terima kargo selesai di gudang buyer Rotterdam. Uji mutu cupping diverifikasi (Score 88.0). Pencairan escrow 100% final disetujui.',
        ], (int) $operator->id);

        $shipment->refresh();
        $this->assertEquals('DELIVERED', $shipment->status);

        // Step 7: Verify final status and public API response
        $apiResponse = $this->getJson("/api/v1/shipments/{$shipment->tracking_number}");
        $apiResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'tracking_number' => 'CTG-TEST-E2E-9999',
                    'status' => 'DELIVERED',
                    'is_delayed' => false,
                ]
            ]);
    }
}
