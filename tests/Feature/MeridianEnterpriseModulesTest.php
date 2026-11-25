<?php

namespace Tests\Feature;

use App\Models\Carrier;
use App\Models\ExportProduct;
use App\Models\Location;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\SupportTicket;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeridianEnterpriseModulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_five_indonesian_islands_have_one_seaport_and_one_airport_plus_us_hubs(): void
    {
        $expectedPorts = [
            'IDBLW' => ['island' => 'Sumatra', 'type' => 'PORT'],
            'IDKNO' => ['island' => 'Sumatra', 'type' => 'AIRPORT'],
            'IDTPR' => ['island' => 'Jawa', 'type' => 'PORT'],
            'IDCGK' => ['island' => 'Jawa', 'type' => 'AIRPORT'],
            'IDPNK' => ['island' => 'Kalimantan', 'type' => 'PORT'],
            'WIOO'  => ['island' => 'Kalimantan', 'type' => 'AIRPORT'],
            'IDMAK' => ['island' => 'Sulawesi', 'type' => 'PORT'],
            'IDUPG' => ['island' => 'Sulawesi', 'type' => 'AIRPORT'],
            'IDDJJ' => ['island' => 'Papua', 'type' => 'PORT'],
            'WAJJ'  => ['island' => 'Papua', 'type' => 'AIRPORT'],
            'USLAX' => ['island' => 'Internasional', 'type' => 'PORT'],
            'USJFK' => ['island' => 'Internasional', 'type' => 'AIRPORT'],
        ];

        foreach ($expectedPorts as $code => $meta) {
            $loc = Location::where('code', $code)->first();
            $this->assertNotNull($loc, "Location {$code} must exist");
            $this->assertEquals($meta['island'], $loc->island);
            $this->assertEquals($meta['type'], $loc->type);
        }

        // Old inland Takengon warehouse must not pollute Sumatra ports
        $this->assertNull(Location::where('code', 'IDTAK')->first());
    }

    public function test_nusantara_coffee_directory_covers_all_five_islands_without_price(): void
    {
        $res = $this->getJson('/api/v1/export-products');
        $res->assertStatus(200);

        $products = $res->json('data');
        $this->assertGreaterThanOrEqual(8, count($products));

        $islands = array_unique(array_column($products, 'island'));
        foreach (['Sumatra', 'Jawa', 'Kalimantan', 'Sulawesi', 'Papua'] as $island) {
            $this->assertContains($island, $islands);
        }

        // Verify Bengkulu Curup, Kalimantan Liberika, Papua Wamena exist and NO price field
        $names = implode(' | ', array_column($products, 'name'));
        $this->assertStringContainsString('Curup', $names);
        $this->assertStringContainsString('Kayong Utara', $names);
        $this->assertStringContainsString('Wamena', $names);
        $this->assertArrayNotHasKey('price', $products[0]);
        $this->assertArrayNotHasKey('price_usd', $products[0]);
    }

    public function test_profile_update_public_visibility_and_admin_verification(): void
    {
        $shipper = User::where('email', 'shipper@cargotrace.com')->firstOrFail();
        $admin = User::where('email', 'admin@cargotrace.com')->firstOrFail();

        $this->actingAs($shipper)->putJson('/api/v1/profile', [
            'phone' => '+62 811 9999 0001',
            'company_name' => 'PT Kopi Aceh Jaya',
            'city' => 'Lhokseumawe',
            'identity_number' => 'NIB-999888777',
        ])->assertStatus(200);

        $pub = $this->getJson("/api/v1/users/{$shipper->id}/public-profile");
        $pub->assertStatus(200)
            ->assertJsonPath('data.phone', '+62 811 9999 0001')
            ->assertJsonPath('data.company_name', 'PT Kopi Aceh Jaya');

        // Admin can toggle identity verification badge
        $initial = (bool) $shipper->fresh()->is_verified;
        $this->actingAs($admin)->patchJson("/api/v1/admin/users/{$shipper->id}/verify")
            ->assertStatus(200)
            ->assertJsonPath('data.is_verified', !$initial);
    }

    public function test_shipper_call_operator_dispatch_physical_verification_chat_and_release_doc(): void
    {
        $shipper = User::where('email', 'shipper@cargotrace.com')->firstOrFail();
        $buyer = User::where('email', 'buyer@cargotrace.com')->firstOrFail();
        $operator = User::where('email', 'operator.sumatra@cargotrace.com')->firstOrFail();
        $belawan = Location::where('code', 'IDBLW')->firstOrFail();
        $kualanamu = Location::where('code', 'IDKNO')->firstOrFail();
        $usJfk = Location::where('code', 'USJFK')->firstOrFail();
        $airCarrier = Carrier::where('transport_mode', 'AIR')->firstOrFail();

        // 1. Buyer & Shipper negotiate via direct Chat
        $this->actingAs($buyer)->postJson('/api/v1/chats/messages', [
            'recipient_id' => $shipper->id,
            'message' => 'Halo, saya butuh 500 karung Kopi Gayo tiba di Amerika dalam 5 hari.',
        ])->assertStatus(201);

        $this->actingAs($shipper)->getJson("/api/v1/chats/messages?peer_id={$buyer->id}")
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 2. Shipper clicks "Buat Pengiriman" to call Operator (target 5 days -> auto AIR!)
        $pkpRes = $this->actingAs($shipper)->postJson('/api/v1/pickup-requests', [
            'buyer_id' => $buyer->id,
            'branch_location_id' => $kualanamu->id,
            'destination_location_id' => $usJfk->id,
            'pickup_city' => 'Lhokseumawe, Aceh',
            'pickup_address' => 'Gudang Kopi Jl. Merdeka No. 12, Lhokseumawe',
            'target_days' => 5,
            'agreed_escrow_usd' => 35000,
            'commodity_summary' => 'Kopi Arabika Gayo Grade 1 (500 karung)',
            'scheduled_note' => 'Siap dicek besok pagi jam 09.00',
        ]);
        $pkpRes->assertStatus(201)
            ->assertJsonPath('data.preferred_mode', 'AIR')
            ->assertJsonPath('data.status', 'PENDING_INSPECTION');

        $pickupId = $pkpRes->json('data.id');

        // 3. Operator dispatches internal branch truck to Lhokseumawe
        $this->actingAs($operator)->patchJson("/api/v1/pickup-requests/{$pickupId}/dispatch")
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'OPERATOR_DISPATCHED');

        // 4. Operator inspects goods & registers official shipment with verification checklist
        $shipRes = $this->actingAs($operator)->postJson('/api/v1/shipments', [
            'pickup_request_id' => $pickupId,
            'customer_id' => $shipper->id,
            'buyer_id' => $buyer->id,
            'carrier_id' => $airCarrier->id,
            'origin_location_id' => $kualanamu->id,
            'destination_location_id' => $usJfk->id,
            'transport_mode' => 'AIR',
            'pickup_address' => 'Lhokseumawe, Aceh',
            'pickup_latitude' => 5.1801,
            'pickup_longitude' => 97.1507,
            'seal_number' => 'SEAL-KNO-8891',
            'verification_checklist' => [
                'content_matched' => true,
                'weight_verified' => true,
                'packaging_dry' => true,
                'seal_intact' => true,
                'documents_complete' => true,
            ],
            'cargo_description' => 'Kopi Arabika Gayo Grade 1 — Kirim Udara Ekspres',
            'weight_kg' => 5000,
            'contract_amount_usd' => 35000,
            'expected_departure' => Carbon::now()->addHours(6)->toDateTimeString(),
            'expected_arrival' => Carbon::now()->addDays(5)->toDateTimeString(),
        ]);
        $shipRes->assertStatus(201)
            ->assertJsonPath('data.transport_mode', 'AIR')
            ->assertJsonPath('data.seal_number', 'SEAL-KNO-8891');

        $shipmentId = $shipRes->json('data.id');
        $this->assertEquals('CONVERTED_TO_SHIPMENT', PickupRequest::find($pickupId)->status);

        // 5. Operator/Shipper issues Digital Release Document (Air Waybill)
        $docRes = $this->actingAs($operator)->postJson("/api/v1/shipments/{$shipmentId}/release-document", [
            'release_document_type' => 'AIR_WAYBILL',
        ]);
        $docRes->assertStatus(200);
        $this->assertStringStartsWith('AWB-', (string) $docRes->json('data.release_document_number'));

        // 6. Demo Time Travel (+1 Hari / Besok) advances simulated_days_elapsed
        $ttRes = $this->actingAs($operator)->postJson('/api/v1/demo/time-travel', [
            'shipment_id' => $shipmentId,
            'hours' => 24,
        ]);
        $ttRes->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, (int) Shipment::find($shipmentId)->simulated_days_elapsed);
    }

    public function test_admin_support_ticket_submission_and_reply(): void
    {
        $admin = User::where('email', 'admin@cargotrace.com')->firstOrFail();
        $buyer = User::where('email', 'buyer@cargotrace.com')->firstOrFail();

        // Public guest can submit Bantuan Admin
        $guestRes = $this->postJson('/api/v1/support-tickets', [
            'guest_name' => 'Pengunjung Publik',
            'guest_contact' => '08123456789',
            'category' => 'UMUM',
            'subject' => 'Cara cek resi kargo',
            'message' => 'Bagaimana cara melacak pengiriman saya?',
        ]);
        $guestRes->assertStatus(201);

        // Authenticated Buyer submits ticket and Admin replies
        $buyRes = $this->actingAs($buyer)->postJson('/api/v1/support-tickets', [
            'category' => 'REKENING_JAMINAN',
            'subject' => 'Konfirmasi Jaminan Dana',
            'message' => 'Mohon bantu cek status dana jaminan kami.',
        ]);
        $buyRes->assertStatus(201);
        $ticketId = $buyRes->json('data.id');

        $this->actingAs($admin)->postJson("/api/v1/admin/support-tickets/{$ticketId}/reply", [
            'admin_reply' => 'Dana jaminan Anda tercatat aman dan sedang dalam perlindungan sistem.',
            'status' => 'RESOLVED',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'RESOLVED');

        $this->assertEquals(2, SupportTicket::count());
    }
}
