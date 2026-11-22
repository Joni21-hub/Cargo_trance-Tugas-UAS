<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for live demo and testing.
     */
    public function run(): void
    {
        // 1. User Accounts (Demo Accounts with RBAC Roles)
        $admin = User::firstOrCreate(
            ['email' => 'admin@cargotrace.com'],
            [
                'name' => 'Logistics Administrator',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );

        $operator = User::firstOrCreate(
            ['email' => 'operator@cargotrace.com'],
            [
                'name' => 'Port Operator & Escrow Clearance Officer',
                'password' => Hash::make('password'),
                'role' => 'operator',
            ]
        );

        $surveyor = User::firstOrCreate(
            ['email' => 'surveyor@cargotrace.com'],
            [
                'name' => 'Field Tally Clerk & Cargo Surveyor (JICT Priok)',
                'password' => Hash::make('password'),
                'role' => 'surveyor',
            ]
        );

        // 1c. Shipper (Pengirim Kargo)
        $customer = User::firstOrCreate(
            ['email' => 'shipper@cargotrace.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Shipper)',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        // Alias akun Shipper untuk kemudahan login demo
        User::firstOrCreate(
            ['email' => 'shipper@gmail.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Shipper)',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        User::firstOrCreate(
            ['email' => 'customer@cargotrace.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Shipper)',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        $koperasi = User::firstOrCreate(
            ['email' => 'koperasi@cargotrace.com'],
            [
                'name' => 'Koperasi Baitul Qiradh Baburrayyan (Takengon, Gayo)',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        // 1d. Buyer (Penerima Kargo & Escrow Funder)
        $buyer = User::firstOrCreate(
            ['email' => 'buyer@cargotrace.com'],
            [
                'name' => 'Global Specialty Coffee Buyer & Consignee',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'wallet_balance_usd' => 500000.00,
            ]
        );
        if ((float) $buyer->wallet_balance_usd <= 0) {
            $buyer->update(['wallet_balance_usd' => 500000.00]);
        }

        // Alias akun Buyer untuk kemudahan login demo
        User::firstOrCreate(
            ['email' => 'buyer@gmail.com'],
            [
                'name' => 'Global Specialty Coffee Buyer & Consignee',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'wallet_balance_usd' => 500000.00,
            ]
        );

        $buyerRotterdam = User::firstOrCreate(
            ['email' => 'buyer.rotterdam@cargotrace.com'],
            [
                'name' => 'Rotterdam Specialty Coffee Traders B.V.',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        $buyerHamburg = User::firstOrCreate(
            ['email' => 'buyer.hamburg@cargotrace.com'],
            [
                'name' => 'Hanseatic Coffee Merchants Hamburg GmbH',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        $buyerTokyo = User::firstOrCreate(
            ['email' => 'buyer.tokyo@cargotrace.com'],
            [
                'name' => 'Tokyo Ginza Specialty Coffee Roastery Co.',
                'password' => Hash::make('password'),
                'role' => 'customer',
            ]
        );

        // 2. Master Locations (UN/LOCODE)
        // Required: Tanjung Priok, Singapore, Rotterdam, Hamburg, Shanghai (+ Belawan, Takengon, Tokyo)
        $priok = Location::firstOrCreate(
            ['code' => 'IDTPR'],
            [
                'name' => 'Port of Tanjung Priok (Jakarta)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'latitude' => -6.1018000,
                'longitude' => 106.8836000,
            ]
        );

        if (!$operator->location_id) {
            $operator->update(['location_id' => $priok->id]);
        }

        $singapore = Location::firstOrCreate(
            ['code' => 'SGSIN'],
            [
                'name' => 'Port of Singapore',
                'type' => 'PORT',
                'country' => 'Singapore',
                'latitude' => 1.2902700,
                'longitude' => 103.8519590,
            ]
        );

        $rotterdam = Location::firstOrCreate(
            ['code' => 'NLRTM'],
            [
                'name' => 'Port of Rotterdam',
                'type' => 'PORT',
                'country' => 'Netherlands',
                'latitude' => 51.9244000,
                'longitude' => 4.4777000,
            ]
        );

        $hamburg = Location::firstOrCreate(
            ['code' => 'DEHAM'],
            [
                'name' => 'Port of Hamburg',
                'type' => 'PORT',
                'country' => 'Germany',
                'latitude' => 53.5511000,
                'longitude' => 9.9937000,
            ]
        );

        $shanghai = Location::firstOrCreate(
            ['code' => 'CNSHA'],
            [
                'name' => 'Port of Shanghai',
                'type' => 'PORT',
                'country' => 'China',
                'latitude' => 31.2304000,
                'longitude' => 121.4737000,
            ]
        );

        $belawan = Location::firstOrCreate(
            ['code' => 'IDBLW'],
            [
                'name' => 'Port of Belawan (Medan / Deli Serdang)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'latitude' => 3.7836000,
                'longitude' => 98.6881000,
            ]
        );

        $takengon = Location::firstOrCreate(
            ['code' => 'IDTAK'],
            [
                'name' => 'Takengon Gayo Highland Hub',
                'type' => 'WAREHOUSE',
                'country' => 'Indonesia',
                'latitude' => 4.6295000,
                'longitude' => 96.8458000,
            ]
        );

        $tokyo = Location::firstOrCreate(
            ['code' => 'JPTYO'],
            [
                'name' => 'Port of Tokyo',
                'type' => 'PORT',
                'country' => 'Japan',
                'latitude' => 35.6180000,
                'longitude' => 139.7820000,
            ]
        );

        // 3. Master Carriers (International Shipping Lines)
        $maersk = Carrier::firstOrCreate(
            ['code' => 'MAEU'],
            [
                'name' => 'Maersk Line (Reefer Cold Chain)',
                'transport_mode' => 'SEA',
                'contact_email' => 'booking@maersk.com',
                'phone' => '+45 33 63 33 63',
                'is_active' => true,
            ]
        );

        $msc = Carrier::firstOrCreate(
            ['code' => 'MSCU'],
            [
                'name' => 'MSC Mediterranean Shipping',
                'transport_mode' => 'SEA',
                'contact_email' => 'ops@msc.com',
                'phone' => '+41 22 703 8888',
                'is_active' => true,
            ]
        );

        $evergreen = Carrier::firstOrCreate(
            ['code' => 'EGLV'],
            [
                'name' => 'Evergreen Marine Corp',
                'transport_mode' => 'SEA',
                'contact_email' => 'cargo@evergreen-marine.com',
                'phone' => '+886 2 2505 7766',
                'is_active' => true,
            ]
        );

        $one = Carrier::firstOrCreate(
            ['code' => 'ONEU'],
            [
                'name' => 'Ocean Network Express (ONE)',
                'transport_mode' => 'SEA',
                'contact_email' => 'ops@one-line.com',
                'phone' => '+81 3 5843 4000',
                'is_active' => true,
            ]
        );

        $cma = Carrier::firstOrCreate(
            ['code' => 'CMDU'],
            [
                'name' => 'CMA CGM Group',
                'transport_mode' => 'SEA',
                'contact_email' => 'cargo@cma-cgm.com',
                'phone' => '+33 4 88 91 90 00',
                'is_active' => true,
            ]
        );

        $hapag = Carrier::firstOrCreate(
            ['code' => 'HLCU'],
            [
                'name' => 'Hapag-Lloyd',
                'transport_mode' => 'SEA',
                'contact_email' => 'info@hlag.com',
                'phone' => '+49 40 3001 0',
                'is_active' => true,
            ]
        );

        // 4. Skenario 1: Normal / On-Time (Jakarta / Tanjung Priok -> Rotterdam, IN_TRANSIT, no delay)
        $departure1 = Carbon::now()->subDays(6);
        $expectedArrival1 = Carbon::now()->addDays(12);

        $shipment1 = Shipment::firstOrCreate(
            ['tracking_number' => 'CTG-20261101-0001'],
            [
                'container_number' => 'MSKU-782910-1',
                'customer_id' => $customer->id,
                'buyer_id' => $buyer->id,
                'carrier_id' => $maersk->id,
                'origin_location_id' => $priok->id,
                'destination_location_id' => $rotterdam->id,
                'current_location_id' => $singapore->id,
                'status' => 'IN_TRANSIT',
                'cargo_description' => 'Kopi Arabika Gayo Specialty Grade 1 (Fairtrade Organic) - 4x 40ft Reefer (Suhu 19.2°C, RH 58%)',
                'weight_kg' => 43200.00,
                'volume_cbm' => 120.00,
                'expected_departure' => $departure1,
                'expected_arrival' => $expectedArrival1,
                'calculated_eta' => $expectedArrival1,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Kontrak L/C #LC-ROT-2026-881 senilai $388,800 USD. Escrow Milestone: DP 30% dirilis saat booking, B/L verifikasi 40%, sisa final 30% dicairkan saat serah terima di Rotterdam. Rute via Selat Malaka - Terusan Suez. Pelayaran lancar tanpa kendala.',
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'event_type' => 'DEPARTURE'],
            [
                'location_id' => $priok->id,
                'event_time' => $departure1,
                'remarks' => 'Pemuatan 4 unit kontainer reefer kopi Gayo ke kapal MV Maersk di Terminal Petikemas JICT Tanjung Priok.',
                'created_by' => $operator->id,
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'event_type' => 'IN_TRANSIT_CHECKPOINT'],
            [
                'location_id' => $singapore->id,
                'event_time' => Carbon::now()->subDays(2),
                'remarks' => 'Transit Selat Malaka - Bunkering & monitoring genset reefer di PSA Singapore Terminal. Suhu stabil 19.2°C, kelembapan 58%.',
                'created_by' => $operator->id,
            ]
        );

        // 5. Skenario 2: Delayed (Customs Hold 24 jam) (Singapore -> Hamburg, 24h customs hold, flagged delayed)
        $departure2 = Carbon::now()->subDays(4);
        $expectedArrival2 = Carbon::now()->addDays(8);
        $calculatedEta2 = (clone $expectedArrival2)->addHours(24);

        $shipment2 = Shipment::firstOrCreate(
            ['tracking_number' => 'CTG-20261102-0002'],
            [
                'container_number' => 'MEDU-991204-5',
                'customer_id' => $customer->id,
                'buyer_id' => $buyer->id,
                'carrier_id' => $msc->id,
                'origin_location_id' => $singapore->id,
                'destination_location_id' => $hamburg->id,
                'current_location_id' => $singapore->id,
                'status' => 'IN_TRANSIT',
                'cargo_description' => 'Kopi Gayo Wine Process & Peaberry Micro-lot (Koperasi Ketiara) - 2x 20ft Reefer (Suhu 22.8°C, RH 73%)',
                'weight_kg' => 21500.00,
                'volume_cbm' => 58.00,
                'expected_departure' => $departure2,
                'expected_arrival' => $expectedArrival2,
                'calculated_eta' => $calculatedEta2,
                'is_delayed' => true,
                'delay_hours' => 24,
                'notes' => 'Kontrak Escrow #ESC-HAM-2026-992 senilai $215,000 USD. Terkena penahanan kepabeanan (Customs Hold 24 jam) di Singapore untuk karantina phytosanitary & audit manifest (+24 Jam). Pencairan escrow ditahan (HELD) menunggu status clearance.',
            ]
        );

        $event2Departure = TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'event_type' => 'DEPARTURE'],
            [
                'location_id' => $singapore->id,
                'event_time' => $departure2,
                'remarks' => 'Keberangkatan kapal MSC dari Port of Singapore menuju Port of Hamburg.',
                'created_by' => $operator->id,
            ]
        );

        $event2Customs = TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'event_type' => 'CUSTOMS_INSPECTION'],
            [
                'location_id' => $singapore->id,
                'event_time' => Carbon::now()->subHours(20),
                'remarks' => 'Kontainer dialihkan ke area inspeksi kepabeanan karantina jalur merah untuk verifikasi fisik sertifikat phytosanitary & fumigasi (Customs Hold 24 Jam).',
                'created_by' => $operator->id,
            ]
        );

        ShipmentException::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'exception_type' => 'CUSTOMS_HOLD'],
            [
                'tracking_event_id' => $event2Customs->id,
                'severity' => 'HIGH',
                'impact_hours' => 24,
                'description' => 'Customs Hold 24 Jam: Penahanan kontainer oleh otoritas kepabeanan pelabuhan untuk audit karantina phytosanitary dan verifikasi keaslian dokumen ekspor kopi (+24 jam keterlambatan). Escrow hold aktif.',
                'status' => 'OPEN',
            ]
        );

        // 6. Skenario 3: Delivered (Completed shipment with full milestone events & Escrow Disbursed)
        $departure3 = Carbon::now()->subDays(22);
        $arrival3 = Carbon::now()->subDays(1);

        $shipment3 = Shipment::firstOrCreate(
            ['tracking_number' => 'CTG-20261103-0003'],
            [
                'container_number' => 'EGLV-412890-2',
                'customer_id' => $customer->id,
                'buyer_id' => $buyer->id,
                'carrier_id' => $evergreen->id,
                'origin_location_id' => $priok->id,
                'destination_location_id' => $rotterdam->id,
                'current_location_id' => $rotterdam->id,
                'status' => 'DELIVERED',
                'cargo_description' => 'Kopi Arabika Java Preanger & Toraja Washed - 3x 40ft Kontainer (Suhu 18.8°C, RH 56%)',
                'weight_kg' => 48000.00,
                'volume_cbm' => 150.00,
                'expected_departure' => $departure3,
                'expected_arrival' => $arrival3,
                'calculated_eta' => $arrival3,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Kontrak L/C #LC-EUR-441 senilai $412,000 USD. Pengiriman selesai dan diterima di gudang roaster Rotterdam. Uji mutu cupping lab (Score 88.5) disetujui buyer. Pencairan dana escrow 100% lunas ditransfer ke rekening koperasi eksportir.',
            ]
        );

        // Milestone 1: DEPARTURE
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'DEPARTURE'],
            [
                'location_id' => $priok->id,
                'event_time' => $departure3,
                'remarks' => 'Pemuatan kontainer dan keberangkatan kapal MV Evergreen dari Dermaga JICT Pelabuhan Tanjung Priok.',
                'created_by' => $operator->id,
            ]
        );

        // Milestone 2: IN_TRANSIT_CHECKPOINT
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'IN_TRANSIT_CHECKPOINT'],
            [
                'location_id' => $singapore->id,
                'event_time' => Carbon::now()->subDays(14),
                'remarks' => 'Transit singgah logistik bahan bakar dan inspeksi transhipment di Port of Singapore.',
                'created_by' => $operator->id,
            ]
        );

        // Milestone 3: ARRIVAL
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'ARRIVAL'],
            [
                'location_id' => $rotterdam->id,
                'event_time' => Carbon::now()->subDays(2),
                'remarks' => 'Kapal tiba dan bersandar di Dermaga APM Terminals Pelabuhan Rotterdam.',
                'created_by' => $operator->id,
            ]
        );

        // Milestone 4: CUSTOMS_INSPECTION
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'CUSTOMS_INSPECTION'],
            [
                'location_id' => $rotterdam->id,
                'event_time' => Carbon::now()->subDays(2)->addHours(4),
                'remarks' => 'Pemeriksaan kepabeanan bea cukai Uni Eropa selesai. Dokumen impor dan karantina disetujui (Cleared Hijau).',
                'created_by' => $operator->id,
            ]
        );

        // Milestone 5: OUT_FOR_DELIVERY
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'OUT_FOR_DELIVERY'],
            [
                'location_id' => $rotterdam->id,
                'event_time' => Carbon::now()->subDays(1)->subHours(8),
                'remarks' => 'Kontainer dikeluarkan dari pelabuhan via inland hauler menuju fasilitas warehouse specialty coffee consignee.',
                'created_by' => $operator->id,
            ]
        );

        // Milestone 6: DELIVERED
        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'event_type' => 'DELIVERED'],
            [
                'location_id' => $rotterdam->id,
                'event_time' => $arrival3,
                'remarks' => 'Bongkar muat selesai di gudang pembeli Rotterdam. Tanda terima sah, sertifikasi mutu cupping (Score 88.5) diverifikasi oleh Buyer, dan pencairan dana escrow 100% resmi disetujui.',
                'created_by' => $operator->id,
            ]
        );

        // 7. Skenario 4: CREATED (Baru diinput oleh Operator, Menunggu Konfirmasi Shipper)
        $departure4 = Carbon::now()->addDays(2);
        $arrival4 = Carbon::now()->addDays(24);

        $shipment4 = Shipment::firstOrCreate(
            ['tracking_number' => 'CTG-20261104-0004'],
            [
                'container_number' => 'CSQU-305438-3',
                'customer_id' => $customer->id,
                'buyer_id' => $buyer->id,
                'carrier_id' => $one->id,
                'origin_location_id' => $priok->id,
                'destination_location_id' => $rotterdam->id,
                'current_location_id' => $priok->id,
                'status' => 'CREATED',
                'cargo_description' => 'Kopi Arabika Mandheling Lintong Grade 1 (Double Picked) - 1x 20ft Kontainer',
                'weight_kg' => 19200.00,
                'volume_cbm' => 33.00,
                'expected_departure' => $departure4,
                'expected_arrival' => $arrival4,
                'calculated_eta' => $arrival4,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Barang baru diinput oleh Operator di dermaga. Menunggu konfirmasi dan persetujuan dari pihak Shipper sebelum kontainer diberangkatkan.',
            ]
        );

        // 8. Seeding Detail Barang (Shipment Items)
        \App\Models\ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'item_name' => 'Kopi Arabika Gayo Green Beans Grade 1'],
            ['category' => 'Kopi', 'quantity' => 720, 'weight_kg' => 43200.00, 'condition' => 'BAIK & SEGEL UTUH', 'notes' => 'Karung goni bermerek Fairtrade Organic dengan lapisan GrainPro.']
        );

        \App\Models\ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'item_name' => 'Kopi Gayo Wine Process Micro-lot'],
            ['category' => 'Kopi', 'quantity' => 350, 'weight_kg' => 21500.00, 'condition' => 'BAIK & TERSEGEL', 'notes' => 'Suhu reefer terjaga pada 22.8°C.']
        );

        \App\Models\ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'item_name' => 'Kopi Arabika Java Preanger Washed'],
            ['category' => 'Kopi', 'quantity' => 800, 'weight_kg' => 48000.00, 'condition' => 'BAIK (DITERIMA LENGKAP)', 'notes' => 'Diterima di gudang pembeli Rotterdam.']
        );

        \App\Models\ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment4->id, 'item_name' => 'Kopi Mandheling Lintong Double Picked'],
            ['category' => 'Kopi', 'quantity' => 320, 'weight_kg' => 19200.00, 'condition' => 'BAIK (MENUNGGU KONFIRMASI SHIPPER)', 'notes' => 'Barang siap muat di Tanjung Priok.']
        );
    }
}
