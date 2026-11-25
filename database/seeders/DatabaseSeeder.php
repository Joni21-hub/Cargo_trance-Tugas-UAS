<?php

namespace Database\Seeders;

use App\Models\Carrier;
use App\Models\ExportProduct;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database for live demo and automated testing.
     */
    public function run(): void
    {
        // ====================================================================
        // 1. MASTER LOCATIONS (5 PULAU INTI INDONESIA: 1 PELABUHAN + 1 BANDARA)
        //    + MITRA TUJUAN DUNIA (AMERIKA SERIKAT, EROPA, ASIA)
        // ====================================================================
        // Hapus lokasi gudang darat lama (IDTAK) jika masih tersisa agar Sumatra tepat 1 pelabuhan + 1 bandara
        Location::where('code', 'IDTAK')->delete();

        // --- PULAU SUMATRA ---
        $belawan = Location::updateOrCreate(
            ['code' => 'IDBLW'],
            [
                'name' => 'Pelabuhan Belawan (Medan · Sumatra)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'island' => 'Sumatra',
                'latitude' => 3.7836000,
                'longitude' => 98.6881000,
            ]
        );
        $kualanamu = Location::updateOrCreate(
            ['code' => 'IDKNO'],
            [
                'name' => 'Bandara Kargo Kualanamu (Medan · Sumatra)',
                'type' => 'AIRPORT',
                'country' => 'Indonesia',
                'island' => 'Sumatra',
                'latitude' => 3.6422000,
                'longitude' => 98.8853000,
            ]
        );

        // --- PULAU JAWA ---
        $priok = Location::updateOrCreate(
            ['code' => 'IDTPR'],
            [
                'name' => 'Pelabuhan Tanjung Priok (Jakarta · Jawa)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'island' => 'Jawa',
                'latitude' => -6.1018000,
                'longitude' => 106.8836000,
            ]
        );
        $soetta = Location::updateOrCreate(
            ['code' => 'IDCGK'],
            [
                'name' => 'Bandara Kargo Soekarno-Hatta (Jakarta · Jawa)',
                'type' => 'AIRPORT',
                'country' => 'Indonesia',
                'island' => 'Jawa',
                'latitude' => -6.1256000,
                'longitude' => 106.6558000,
            ]
        );

        // --- PULAU KALIMANTAN ---
        $pontianak = Location::updateOrCreate(
            ['code' => 'IDPNK'],
            [
                'name' => 'Pelabuhan Pontianak (Kalimantan Barat)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'island' => 'Kalimantan',
                'latitude' => -0.0160000,
                'longitude' => 109.3320000,
            ]
        );
        $supadio = Location::updateOrCreate(
            ['code' => 'WIOO'],
            [
                'name' => 'Bandara Kargo Supadio (Pontianak · Kalimantan)',
                'type' => 'AIRPORT',
                'country' => 'Indonesia',
                'island' => 'Kalimantan',
                'latitude' => -0.1507000,
                'longitude' => 109.4039000,
            ]
        );

        // --- PULAU SULAWESI ---
        $makassar = Location::updateOrCreate(
            ['code' => 'IDMAK'],
            [
                'name' => 'Pelabuhan Makassar (Sulawesi Selatan)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'island' => 'Sulawesi',
                'latitude' => -5.1166000,
                'longitude' => 119.4000000,
            ]
        );
        $hasanuddin = Location::updateOrCreate(
            ['code' => 'IDUPG'],
            [
                'name' => 'Bandara Kargo Sultan Hasanuddin (Makassar · Sulawesi)',
                'type' => 'AIRPORT',
                'country' => 'Indonesia',
                'island' => 'Sulawesi',
                'latitude' => -5.0616000,
                'longitude' => 119.5540000,
            ]
        );

        // --- PULAU PAPUA ---
        $jayapura = Location::updateOrCreate(
            ['code' => 'IDDJJ'],
            [
                'name' => 'Pelabuhan Jayapura (Papua)',
                'type' => 'PORT',
                'country' => 'Indonesia',
                'island' => 'Papua',
                'latitude' => -2.5333000,
                'longitude' => 140.7000000,
            ]
        );
        $sentani = Location::updateOrCreate(
            ['code' => 'WAJJ'],
            [
                'name' => 'Bandara Kargo Sentani (Jayapura · Papua)',
                'type' => 'AIRPORT',
                'country' => 'Indonesia',
                'island' => 'Papua',
                'latitude' => -2.5769000,
                'longitude' => 140.5164000,
            ]
        );

        // --- TUJUAN EKSPOR DUNIA (AMERIKA SERIKAT, EROPA, ASIA) ---
        $losAngeles = Location::updateOrCreate(
            ['code' => 'USLAX'],
            [
                'name' => 'Pelabuhan & Terminal Los Angeles (Amerika Serikat)',
                'type' => 'PORT',
                'country' => 'Amerika Serikat',
                'island' => 'Internasional',
                'latitude' => 33.7405000,
                'longitude' => -118.2720000,
            ]
        );
        $jfkAirport = Location::updateOrCreate(
            ['code' => 'USJFK'],
            [
                'name' => 'Bandara Kargo JFK New York (Amerika Serikat)',
                'type' => 'AIRPORT',
                'country' => 'Amerika Serikat',
                'island' => 'Internasional',
                'latitude' => 40.6413000,
                'longitude' => -73.7781000,
            ]
        );
        $singapore = Location::updateOrCreate(
            ['code' => 'SGSIN'],
            [
                'name' => 'Pelabuhan Singapore (Singapura)',
                'type' => 'PORT',
                'country' => 'Singapura',
                'island' => 'Internasional',
                'latitude' => 1.2902700,
                'longitude' => 103.8519590,
            ]
        );
        $rotterdam = Location::updateOrCreate(
            ['code' => 'NLRTM'],
            [
                'name' => 'Pelabuhan Rotterdam (Belanda)',
                'type' => 'PORT',
                'country' => 'Belanda',
                'island' => 'Internasional',
                'latitude' => 51.9244000,
                'longitude' => 4.4777000,
            ]
        );
        $hamburg = Location::updateOrCreate(
            ['code' => 'DEHAM'],
            [
                'name' => 'Pelabuhan Hamburg (Jerman)',
                'type' => 'PORT',
                'country' => 'Jerman',
                'island' => 'Internasional',
                'latitude' => 53.5511000,
                'longitude' => 9.9937000,
            ]
        );
        $shanghai = Location::updateOrCreate(
            ['code' => 'CNSHA'],
            [
                'name' => 'Pelabuhan Shanghai (Tiongkok)',
                'type' => 'PORT',
                'country' => 'Tiongkok',
                'island' => 'Internasional',
                'latitude' => 31.2304000,
                'longitude' => 121.4737000,
            ]
        );
        $tokyo = Location::updateOrCreate(
            ['code' => 'JPTYO'],
            [
                'name' => 'Pelabuhan Tokyo (Jepang)',
                'type' => 'PORT',
                'country' => 'Jepang',
                'island' => 'Internasional',
                'latitude' => 35.6180000,
                'longitude' => 139.7820000,
            ]
        );

        // ====================================================================
        // 2. AKUN PENGGUNA & PROFIL IDENTITAS TERVERIFIKASI
        // ====================================================================
        $admin = User::updateOrCreate(
            ['email' => 'admin@cargotrace.com'],
            [
                'name' => 'Administrator Utama Meridian',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '+62 21 5088 9100',
                'company_name' => 'PT Meridian Logistik & Jaminan Nusantara',
                'address' => 'Jl. Yos Sudarso Kav. 28, Tanjung Priok',
                'city' => 'Jakarta Utara',
                'country' => 'Indonesia',
                'identity_number' => 'NIB-0192837465001',
                'bio' => 'Pengelola utama verifikasi identitas, jaringan 5 pulau, dan pusat bantuan transaksi.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        $operator = User::updateOrCreate(
            ['email' => 'operator@cargotrace.com'],
            [
                'name' => 'Budi Santoso (Petugas Cabang Jawa · Tanjung Priok)',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'location_id' => $priok->id,
                'phone' => '+62 812 8801 4401',
                'company_name' => 'Meridian Cabang Jawa — Tanjung Priok & Soetta',
                'address' => 'Terminal Petikemas JICT Gate 2, Tanjung Priok',
                'city' => 'Jakarta Utara',
                'country' => 'Indonesia',
                'identity_number' => 'OPS-IDTPR-001',
                'bio' => 'Petugas pemeriksaan fisik barang, timbang sah, segel kontainer, dan penjemputan armada darat Pulau Jawa.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // Petugas Cabang 4 Pulau Lainnya (Sumatra, Kalimantan, Sulawesi, Papua)
        User::updateOrCreate(
            ['email' => 'operator.sumatra@cargotrace.com'],
            [
                'name' => 'Rizal Harahap (Petugas Cabang Sumatra · Belawan)',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'location_id' => $belawan->id,
                'phone' => '+62 813 6102 7788',
                'company_name' => 'Meridian Cabang Sumatra — Belawan & Kualanamu',
                'address' => 'Jl. Raya Pelabuhan Belawan No. 14, Medan',
                'city' => 'Medan',
                'country' => 'Indonesia',
                'identity_number' => 'OPS-IDBLW-002',
                'bio' => 'Melayani penjemputan armada truk darat Aceh (Lhokseumawe/Gayo), Sumut, hingga Bengkulu ke Belawan & Kualanamu.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'operator.kalimantan@cargotrace.com'],
            [
                'name' => 'Hendra Wijaya (Petugas Cabang Kalimantan · Pontianak)',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'location_id' => $pontianak->id,
                'phone' => '+62 852 4510 2233',
                'company_name' => 'Meridian Cabang Kalimantan — Pontianak & Supadio',
                'address' => 'Jl. Pak Kasih No. 11, Pelabuhan Dwikora Pontianak',
                'city' => 'Pontianak',
                'country' => 'Indonesia',
                'identity_number' => 'OPS-IDPNK-003',
                'bio' => 'Petugas verifikasi & penjemputan komoditas Kopi Liberika Kayong Utara dan Kapuas Hulu.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'operator.sulawesi@cargotrace.com'],
            [
                'name' => 'Andi Pratama (Petugas Cabang Sulawesi · Makassar)',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'location_id' => $makassar->id,
                'phone' => '+62 811 4120 9981',
                'company_name' => 'Meridian Cabang Sulawesi — Makassar & Hasanuddin',
                'address' => 'Jl. Soekarno No. 1, Pelabuhan Makassar',
                'city' => 'Makassar',
                'country' => 'Indonesia',
                'identity_number' => 'OPS-IDMAK-004',
                'bio' => 'Petugas pemeriksaan dan penjemputan kopi Toraja, Kalosi Enrekang, dan Mamasa.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'operator.papua@cargotrace.com'],
            [
                'name' => 'Yohanes Wenda (Petugas Cabang Papua · Jayapura)',
                'password' => Hash::make('password'),
                'role' => 'operator',
                'location_id' => $jayapura->id,
                'phone' => '+62 812 4800 5541',
                'company_name' => 'Meridian Cabang Papua — Jayapura & Sentani',
                'address' => 'Jl. Koti No. 8, Kawasan Pelabuhan Jayapura',
                'city' => 'Jayapura',
                'country' => 'Indonesia',
                'identity_number' => 'OPS-IDDJJ-005',
                'bio' => 'Petugas verifikasi dan pengangkutan Kopi Arabika Wamena Lembah Baliem menuju Pelabuhan Jayapura & Bandara Sentani.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        $surveyor = User::updateOrCreate(
            ['email' => 'surveyor@cargotrace.com'],
            [
                'name' => 'Petugas Pemeriksa Mutu Lapangan',
                'password' => Hash::make('password'),
                'role' => 'surveyor',
                'location_id' => $priok->id,
                'phone' => '+62 812 9000 1122',
                'company_name' => 'Divisi Mutu & Segel Meridian',
                'city' => 'Jakarta Utara',
                'country' => 'Indonesia',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // 2c. Shipper Utama (Pengirim / Eksportir Kopi)
        $customer = User::updateOrCreate(
            ['email' => 'shipper@cargotrace.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Pengirim)',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+62 811 6701 8822',
                'company_name' => 'PT Samudera Kopi Nusantara Ekspor',
                'address' => 'Jl. Merdeka Barat No. 45, Kota Lhokseumawe, Aceh',
                'city' => 'Lhokseumawe, Aceh',
                'country' => 'Indonesia',
                'identity_number' => 'NIB-9120308471922 / NPWP 84.219.331.4-102.000',
                'bio' => 'Eksportir resmi biji kopi mentah Nusantara dari sentra petani Aceh, Bengkulu, Jawa, Kalimantan, Sulawesi, dan Papua.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // Alias kompatibilitas test & variasi login
        User::updateOrCreate(
            ['email' => 'shipper@gmail.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Pengirim)',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+62 811 6701 8822',
                'company_name' => 'PT Samudera Kopi Nusantara Ekspor',
                'city' => 'Lhokseumawe, Aceh',
                'country' => 'Indonesia',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'customer@cargotrace.com'],
            [
                'name' => 'PT Samudera Kopi Nusantara (Pengirim)',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+62 811 6701 8822',
                'company_name' => 'PT Samudera Kopi Nusantara Ekspor',
                'city' => 'Lhokseumawe, Aceh',
                'country' => 'Indonesia',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        $koperasi = User::updateOrCreate(
            ['email' => 'koperasi@cargotrace.com'],
            [
                'name' => 'Koperasi Petani Kopi Gayo & Curup',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+62 812 6990 3311',
                'company_name' => 'Koperasi Baitul Qiradh Baburrayyan',
                'address' => 'Jl. Lebe Kader No. 18, Takengon, Aceh Tengah',
                'city' => 'Takengon, Aceh',
                'country' => 'Indonesia',
                'identity_number' => 'NIB-8120001928374',
                'bio' => 'Koperasi binaan petani kopi dataran tinggi Gayo dan Rejang Lebong Curup (Fairtrade & Organic).',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // 2d. Buyer Utama (Pembeli / Importir Luar Negeri)
        $buyer = User::updateOrCreate(
            ['email' => 'buyer@cargotrace.com'],
            [
                'name' => 'Pacific Roasters USA (Pembeli)',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+1 310 555 0194',
                'company_name' => 'Pacific Specialty Coffee Importers LLC',
                'address' => '742 Harbor Blvd, Suite 310, San Pedro, CA 90731',
                'city' => 'Los Angeles',
                'country' => 'Amerika Serikat',
                'identity_number' => 'US-EIN-94-3829104',
                'bio' => 'Importir dan roastery kopi spesialti Nusantara untuk pasar Amerika Serikat & Eropa.',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );
        if ((float) $buyer->wallet_balance_usd <= 0) {
            $buyer->update(['wallet_balance_usd' => 500000.00]);
        }

        User::updateOrCreate(
            ['email' => 'buyer@gmail.com'],
            [
                'name' => 'Pacific Roasters USA (Pembeli)',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+1 310 555 0194',
                'company_name' => 'Pacific Specialty Coffee Importers LLC',
                'city' => 'Los Angeles',
                'country' => 'Amerika Serikat',
                'is_verified' => true,
                'verified_at' => now(),
                'wallet_balance_usd' => 500000.00,
            ]
        );

        User::updateOrCreate(
            ['email' => 'buyer.rotterdam@cargotrace.com'],
            [
                'name' => 'Rotterdam Specialty Coffee Traders B.V.',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+31 10 489 2210',
                'company_name' => 'Rotterdam Coffee Traders B.V.',
                'city' => 'Rotterdam',
                'country' => 'Belanda',
                'identity_number' => 'NL-KVK-24391827',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'buyer.hamburg@cargotrace.com'],
            [
                'name' => 'Hanseatic Coffee Merchants Hamburg GmbH',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+49 40 369 8810',
                'company_name' => 'Hanseatic Coffee Merchants GmbH',
                'city' => 'Hamburg',
                'country' => 'Jerman',
                'identity_number' => 'DE-HRB-118290',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        User::updateOrCreate(
            ['email' => 'buyer.tokyo@cargotrace.com'],
            [
                'name' => 'Tokyo Ginza Specialty Coffee Roastery Co.',
                'password' => Hash::make('password'),
                'role' => 'customer',
                'phone' => '+81 3 5550 8821',
                'company_name' => 'Ginza Coffee Roastery Japan',
                'city' => 'Tokyo',
                'country' => 'Jepang',
                'identity_number' => 'JP-CORP-90104010',
                'is_verified' => true,
                'verified_at' => now(),
            ]
        );

        // ====================================================================
        // 3. MASTER CARRIERS (MITRA PELAYARAN KAPAL, PESAWAT UDARA & ARMADA DARAT)
        // ====================================================================
        $maersk = Carrier::updateOrCreate(
            ['code' => 'MAEU'],
            [
                'name' => 'Maersk Line (Mitra Kapal Internasional)',
                'transport_mode' => 'SEA',
                'contact_email' => 'booking@maersk.com',
                'phone' => '+45 33 63 33 63',
                'is_active' => true,
            ]
        );
        $msc = Carrier::updateOrCreate(
            ['code' => 'MSCU'],
            [
                'name' => 'MSC Mediterranean Shipping (Mitra Kapal)',
                'transport_mode' => 'SEA',
                'contact_email' => 'ops@msc.com',
                'phone' => '+41 22 703 8888',
                'is_active' => true,
            ]
        );
        $evergreen = Carrier::updateOrCreate(
            ['code' => 'EGLV'],
            [
                'name' => 'Evergreen Marine Corp (Mitra Kapal)',
                'transport_mode' => 'SEA',
                'contact_email' => 'cargo@evergreen-marine.com',
                'phone' => '+886 2 2505 7766',
                'is_active' => true,
            ]
        );
        $one = Carrier::updateOrCreate(
            ['code' => 'ONEU'],
            [
                'name' => 'Ocean Network Express — ONE (Mitra Kapal)',
                'transport_mode' => 'SEA',
                'contact_email' => 'ops@one-line.com',
                'phone' => '+81 3 5843 4000',
                'is_active' => true,
            ]
        );
        Carrier::updateOrCreate(
            ['code' => 'CMDU'],
            [
                'name' => 'CMA CGM Group (Mitra Kapal)',
                'transport_mode' => 'SEA',
                'contact_email' => 'cargo@cma-cgm.com',
                'phone' => '+33 4 88 91 90 00',
                'is_active' => true,
            ]
        );
        Carrier::updateOrCreate(
            ['code' => 'HLCU'],
            [
                'name' => 'Hapag-Lloyd (Mitra Kapal)',
                'transport_mode' => 'SEA',
                'contact_email' => 'info@hlag.com',
                'phone' => '+49 40 3001 0',
                'is_active' => true,
            ]
        );
        // Mitra Pelayaran Nasional Indonesia
        Carrier::updateOrCreate(
            ['code' => 'SMDR'],
            [
                'name' => 'Samudera Indonesia Shipping (Mitra Kapal Nasional)',
                'transport_mode' => 'SEA',
                'contact_email' => 'ekspor@samudera.id',
                'phone' => '+62 21 2567 6999',
                'is_active' => true,
            ]
        );
        Carrier::updateOrCreate(
            ['code' => 'MRTS'],
            [
                'name' => 'Meratus Line Nusantara (Mitra Kapal Nasional)',
                'transport_mode' => 'SEA',
                'contact_email' => 'cargo@meratusline.com',
                'phone' => '+62 31 329 2288',
                'is_active' => true,
            ]
        );
        // Mitra Pesawat Kargo Udara (Untuk Pengiriman Cepat <= 7 Hari)
        Carrier::updateOrCreate(
            ['code' => 'GACG'],
            [
                'name' => 'Garuda Indonesia Cargo (Mitra Pesawat Udara)',
                'transport_mode' => 'AIR',
                'contact_email' => 'cargo@garuda-indonesia.com',
                'phone' => '+62 21 550 1888',
                'is_active' => true,
            ]
        );
        Carrier::updateOrCreate(
            ['code' => 'DHLA'],
            [
                'name' => 'DHL Express Air Cargo (Mitra Pesawat Ekspres)',
                'transport_mode' => 'AIR',
                'contact_email' => 'airfreight@dhl.com',
                'phone' => '+62 21 7917 3333',
                'is_active' => true,
            ]
        );
        // Armada Truk Darat Internal Milik Sendiri
        Carrier::updateOrCreate(
            ['code' => 'MRDTRK'],
            [
                'name' => 'Armada Truk Darat Meridian (Milik Sendiri)',
                'transport_mode' => 'LAND',
                'contact_email' => 'armada@meridian-logistik.id',
                'phone' => '+62 21 5088 9105',
                'is_active' => true,
            ]
        );

        // ====================================================================
        // 4. DIREKTORI KOMODITAS KOPI NUSANTARA (5 PULAU BESAR — TANPA HARGA!)
        //    Non-Marketplace: Buyer lihat komoditas & profil Shipper lalu Chat
        // ====================================================================
        $coffeeDirectory = [
            [
                'name' => 'Kopi Arabika Gayo Grade 1 (Aceh)',
                'island' => 'Sumatra',
                'region' => 'Takengon & Lhokseumawe, Aceh',
                'origin_location_id' => $belawan->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Semi-Washed (Giling Basah) & Full Washed',
                'altitude_mdpl' => '1.400 – 1.700 mdpl',
                'tasting_notes' => 'Rempah hangat, cokelat hitam, karamel, keasaman rendah',
                'supply_capacity' => '25 Ton / Bulan (Siap Ekspor)',
                'description' => 'Biji kopi mentah organik dataran tinggi Gayo dengan kadar air terjaga 11–12% dalam karung GrainPro. Negosiasi harga dan jumlah langsung melalui Chat dengan Pengirim.',
            ],
            [
                'name' => 'Kopi Robusta & Arabika Curup (Bengkulu)',
                'island' => 'Sumatra',
                'region' => 'Curup, Kabupaten Rejang Lebong, Bengkulu',
                'origin_location_id' => $belawan->id,
                'shipper_id' => $koperasi->id,
                'bean_type' => 'Robusta',
                'process_method' => 'Petik Merah Natural & Honey',
                'altitude_mdpl' => '800 – 1.300 mdpl',
                'tasting_notes' => 'Aroma tajam, kekentalan (body) pekat, cokelat gelap & rempah',
                'supply_capacity' => '20 Ton / Bulan',
                'description' => 'Kopi unggulan sentra Curup Rejang Lebong, Bengkulu. Cocok untuk espresso blend ekspor pasar Eropa & Amerika. Diskusikan spesifikasi dan harga langsung bersama Pengirim.',
            ],
            [
                'name' => 'Kopi Arabika Mandheling Lintong (Sumatra Utara)',
                'island' => 'Sumatra',
                'region' => 'Lintong Nihuta & Mandailing, Sumatra Utara',
                'origin_location_id' => $belawan->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Wet-Hulled Double Picked',
                'altitude_mdpl' => '1.200 – 1.500 mdpl',
                'tasting_notes' => 'Herbal manis, cokelat pekat, body penuh & lembut',
                'supply_capacity' => '18 Ton / Bulan',
                'description' => 'Biji kopi sortasi ganda (Double Picked) dari kawasan Danau Toba menuju Pelabuhan Belawan atau Bandara Kualanamu.',
            ],
            [
                'name' => 'Kopi Arabika Java Preanger (Jawa Barat)',
                'island' => 'Jawa',
                'region' => 'Dataran Tinggi Priangan, Bandung, Jawa Barat',
                'origin_location_id' => $priok->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Full Washed & Honey',
                'altitude_mdpl' => '1.300 – 1.650 mdpl',
                'tasting_notes' => 'Bunga segar, gula aren, citrus halus, aftertaste bersih',
                'supply_capacity' => '22 Ton / Bulan',
                'description' => 'Kopi bersejarah tanah Sunda dengan cita rasa seimbang, siap diberangkatkan melalui Pelabuhan Tanjung Priok atau Bandara Soekarno-Hatta.',
            ],
            [
                'name' => 'Kopi Liberika Kayong Utara (Kalimantan Barat)',
                'island' => 'Kalimantan',
                'region' => 'Kabupaten Kayong Utara, Kalimantan Barat',
                'origin_location_id' => $pontianak->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Liberika',
                'process_method' => 'Natural Gambut & Honey',
                'altitude_mdpl' => '0 – 50 mdpl (Lahan Gambut Pesisir)',
                'tasting_notes' => 'Buah nangka & cempedak eksotis, karamel, cokelat susu',
                'supply_capacity' => '10 Ton / Bulan (Indikasi Geografis)',
                'description' => 'Varietas langka Coffea liberica khas lahan gambut Kalimantan Barat yang bersertifikat Indikasi Geografis, siap kirim via Pelabuhan Pontianak atau Bandara Supadio.',
            ],
            [
                'name' => 'Kopi Rakyat Kapuas Hulu & Kutai (Kalimantan)',
                'island' => 'Kalimantan',
                'region' => 'Kapuas Hulu & Kutai, Kalimantan',
                'origin_location_id' => $pontianak->id,
                'shipper_id' => $koperasi->id,
                'bean_type' => 'Robusta',
                'process_method' => 'Natural Tradisional Sortasi Ekspor',
                'altitude_mdpl' => '100 – 450 mdpl',
                'tasting_notes' => 'Kacang panggang, kakao, sentuhan kayu manis',
                'supply_capacity' => '12 Ton / Bulan',
                'description' => 'Kopi perkebunan rakyat sekitar hutan sungai Kalimantan dengan karakter rasa tebal dan aroma kuat.',
            ],
            [
                'name' => 'Kopi Arabika Toraja & Kalosi Enrekang (Sulawesi Selatan)',
                'island' => 'Sulawesi',
                'region' => 'Tana Toraja & Kalosi Enrekang, Sulawesi Selatan',
                'origin_location_id' => $makassar->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Full Washed Pegunungan Latimojong',
                'altitude_mdpl' => '1.400 – 1.850 mdpl',
                'tasting_notes' => 'Rempah kayu manis, earthy bersih, dark chocolate, keasaman seimbang',
                'supply_capacity' => '20 Ton / Bulan',
                'description' => 'Kopi legendaris Celebes Kalossi & Toraja dari lereng Pegunungan Latimojong, dikirim melalui Pelabuhan Makassar atau Bandara Sultan Hasanuddin.',
            ],
            [
                'name' => 'Kopi Arabika Mamasa (Sulawesi Barat)',
                'island' => 'Sulawesi',
                'region' => 'Kabupaten Mamasa, Sulawesi Barat',
                'origin_location_id' => $makassar->id,
                'shipper_id' => $koperasi->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Semi-Washed & Natural',
                'altitude_mdpl' => '1.300 – 1.700 mdpl',
                'tasting_notes' => 'Manis lembut, aroma buah tropis, body tebal',
                'supply_capacity' => '14 Ton / Bulan',
                'description' => 'Kopi dataran tinggi pegunungan Mamasa berbatasan langsung dengan jalur pegunungan Toraja.',
            ],
            [
                'name' => 'Kopi Arabika Wamena Lembah Baliem (Papua)',
                'island' => 'Papua',
                'region' => 'Lembah Baliem, Pegunungan Jayawijaya, Papua',
                'origin_location_id' => $jayapura->id,
                'shipper_id' => $customer->id,
                'bean_type' => 'Arabika',
                'process_method' => 'Full Washed Organik Pegunungan',
                'altitude_mdpl' => '1.500 – 2.000 mdpl',
                'tasting_notes' => 'Aroma bunga (floral), cokelat halus, clean cup, sangat lembut',
                'supply_capacity' => '12 Ton / Bulan (Organik)',
                'description' => 'Ditanam secara organik murni tanpa bahan kimia di Lembah Baliem Papua, diberangkatkan lewat Pelabuhan Jayapura atau Bandara Sentani.',
            ],
        ];

        foreach ($coffeeDirectory as $prod) {
            ExportProduct::updateOrCreate(
                ['name' => $prod['name']],
                $prod
            );
        }

        // ====================================================================
        // 5. DEMO SHIPMENT SCENARIOS (Digunakan oleh PHPUnit LiveDemoSeederAndWorkflowTest)
        //    Catatan: Pada DB lokal live user, baris shipment dapat dikosongkan ke 0 agar demo manual dari awal.
        // ====================================================================
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
                'transport_mode' => 'SEA',
                'cargo_description' => 'Kopi Arabika Gayo Specialty Grade 1 (Fairtrade Organic) - 4x 40ft Reefer',
                'weight_kg' => 43200.00,
                'volume_cbm' => 120.00,
                'expected_departure' => $departure1,
                'expected_arrival' => $expectedArrival1,
                'calculated_eta' => $expectedArrival1,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Pelayaran lancar tanpa kendala menuju Pelabuhan Rotterdam dengan perlindungan Rekening Jaminan (Escrow).',
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'event_type' => 'DEPARTURE'],
            [
                'location_id' => $priok->id,
                'event_time' => $departure1,
                'remarks' => 'Pemuatan kontainer kopi Gayo ke kapal di Pelabuhan Tanjung Priok.',
                'created_by' => $operator->id,
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'event_type' => 'IN_TRANSIT_CHECKPOINT'],
            [
                'location_id' => $singapore->id,
                'event_time' => Carbon::now()->subDays(2),
                'remarks' => 'Transit singgah di Pelabuhan Singapore. Suhu stabil 19.2°C.',
                'created_by' => $operator->id,
            ]
        );

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
                'transport_mode' => 'SEA',
                'cargo_description' => 'Kopi Gayo Wine Process & Peaberry Micro-lot - 2x 20ft Reefer',
                'weight_kg' => 21500.00,
                'volume_cbm' => 58.00,
                'expected_departure' => $departure2,
                'expected_arrival' => $expectedArrival2,
                'calculated_eta' => $calculatedEta2,
                'is_delayed' => true,
                'delay_hours' => 24,
                'notes' => 'Ditahan Bea Cukai (Customs Hold 24 jam) di Singapura untuk pemeriksaan sertifikat ekspor. Pencairan jaminan ditahan sementara.',
            ]
        );

        TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'event_type' => 'DEPARTURE'],
            [
                'location_id' => $singapore->id,
                'event_time' => $departure2,
                'remarks' => 'Keberangkatan kapal MSC dari Pelabuhan Singapore menuju Pelabuhan Hamburg.',
                'created_by' => $operator->id,
            ]
        );

        $event2Customs = TrackingEvent::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'event_type' => 'CUSTOMS_INSPECTION'],
            [
                'location_id' => $singapore->id,
                'event_time' => Carbon::now()->subHours(20),
                'remarks' => 'Pemeriksaan kepabeanan jalur merah untuk audit karantina kopi (+24 Jam).',
                'created_by' => $operator->id,
            ]
        );

        ShipmentException::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'exception_type' => 'CUSTOMS_HOLD'],
            [
                'tracking_event_id' => $event2Customs->id,
                'severity' => 'HIGH',
                'impact_hours' => 24,
                'description' => 'Ditahan Bea Cukai 24 Jam untuk pemeriksaan sertifikat karantina kopi.',
                'status' => 'OPEN',
            ]
        );

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
                'transport_mode' => 'SEA',
                'cargo_description' => 'Kopi Arabika Java Preanger & Toraja Washed - 3x 40ft Kontainer',
                'weight_kg' => 48000.00,
                'volume_cbm' => 150.00,
                'expected_departure' => $departure3,
                'expected_arrival' => $arrival3,
                'calculated_eta' => $arrival3,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Pengiriman selesai diterima di gudang pembeli Rotterdam. Pencairan dana escrow 100% lunas ditransfer ke rekening eksportir.',
            ]
        );

        foreach ([
            ['DEPARTURE', $priok->id, $departure3, 'Keberangkatan kapal dari Pelabuhan Tanjung Priok.'],
            ['IN_TRANSIT_CHECKPOINT', $singapore->id, Carbon::now()->subDays(14), 'Transit di Pelabuhan Singapore.'],
            ['ARRIVAL', $rotterdam->id, Carbon::now()->subDays(2), 'Kapal tiba di Pelabuhan Rotterdam.'],
            ['CUSTOMS_INSPECTION', $rotterdam->id, Carbon::now()->subDays(2)->addHours(4), 'Izin Bea Cukai Uni Eropa disetujui.'],
            ['OUT_FOR_DELIVERY', $rotterdam->id, Carbon::now()->subDays(1)->subHours(8), 'Kontainer diantar truk menuju gudang pembeli.'],
            ['DELIVERED', $rotterdam->id, $arrival3, 'Barang diterima lengkap dan dana jaminan dicairkan ke pengirim.'],
        ] as [$evType, $locId, $evTime, $evRem]) {
            TrackingEvent::firstOrCreate(
                ['shipment_id' => $shipment3->id, 'event_type' => $evType],
                ['location_id' => $locId, 'event_time' => $evTime, 'remarks' => $evRem, 'created_by' => $operator->id]
            );
        }

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
                'transport_mode' => 'SEA',
                'cargo_description' => 'Kopi Arabika Mandheling Lintong Grade 1 - 1x 20ft Kontainer',
                'weight_kg' => 19200.00,
                'volume_cbm' => 33.00,
                'expected_departure' => $departure4,
                'expected_arrival' => $arrival4,
                'calculated_eta' => $arrival4,
                'is_delayed' => false,
                'delay_hours' => 0,
                'notes' => 'Menunggu persetujuan pengirim sebelum kontainer diberangkatkan.',
            ]
        );

        ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment1->id, 'item_name' => 'Kopi Arabika Gayo Green Beans Grade 1'],
            ['category' => 'Kopi', 'quantity' => 720, 'weight_kg' => 43200.00, 'condition' => 'BAIK & SEGEL UTUH']
        );
        ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment2->id, 'item_name' => 'Kopi Gayo Wine Process Micro-lot'],
            ['category' => 'Kopi', 'quantity' => 350, 'weight_kg' => 21500.00, 'condition' => 'BAIK & TERSEGEL']
        );
        ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment3->id, 'item_name' => 'Kopi Arabika Java Preanger Washed'],
            ['category' => 'Kopi', 'quantity' => 800, 'weight_kg' => 48000.00, 'condition' => 'BAIK (DITERIMA LENGKAP)']
        );
        ShipmentItem::firstOrCreate(
            ['shipment_id' => $shipment4->id, 'item_name' => 'Kopi Mandheling Lintong Double Picked'],
            ['category' => 'Kopi', 'quantity' => 320, 'weight_kg' => 19200.00, 'condition' => 'BAIK (MENUNGGU PERSETUJUAN)']
        );
    }
}
