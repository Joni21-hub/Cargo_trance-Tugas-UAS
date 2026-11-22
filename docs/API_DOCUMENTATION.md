# Meridian (CargoTrace) — REST API Documentation

**Base URL:** `http://127.0.0.1:8000/api/v1`  
**Content-Type:** `application/json`  
**Accept:** `application/json`  
**Authentication:** Laravel Session Cookie (cookie jar / `credentials: same-origin`)

> Postman Collection siap impor tersedia di [postman_collection.json](./postman_collection.json).

---

## 1. Format Respons & Kode Status HTTP

Seluruh endpoint mengembalikan amplop JSON yang seragam dari `BaseApiController`:

### Sukses (`200 OK` / `201 Created`)
```json
{
  "success": true,
  "message": "Shipments retrieved successfully",
  "data": {}
}
```

### Error (`401` / `403` / `404` / `422`)
```json
{
  "success": false,
  "message": "Validation failed",
  "errors": {
    "container_number": [
      "The container_number has an invalid ISO 6346 check digit. Expected 3, got 4."
    ]
  }
}
```

| Status Code | Arti | Kapan Terjadi |
| :--- | :--- | :--- |
| `200 OK` | Berhasil | Read, update, delete, konfirmasi, atau resolusi berhasil |
| `201 Created` | Resource dibuat | Registrasi user, pembuatan shipment, checkpoint, exception, carrier, atau location baru |
| `401 Unauthorized` | Belum login | Mengakses route ber-middleware `auth` tanpa sesi valid atau kredensial login salah |
| `403 Forbidden` | Akses ditolak | Role user tidak memenuhi syarat (`role:admin,operator` atau `role:admin`) |
| `404 Not Found` | Tidak ditemukan | `id`, `tracking_number`, atau `container_number` tidak ada di database |
| `422 Unprocessable Entity` | Validasi gagal | Input tidak sesuai aturan `FormRequest`, format ISO 6346 salah, atau transisi status ilegal |

---

## 2. Authentication & Session

### `POST /api/v1/register`
Mendaftarkan akun pelanggan baru (`role` otomatis `customer`) dan langsung membuat sesi login.

- **Akses:** Publik
- **Request Body:**
```json
{
  "name": "PT Nusantara Ekspor",
  "email": "ops@nusantara.id",
  "password": "password123"
}
```
- **Validasi:** `name` (`required|string|max:120`), `email` (`required|email|max:150|unique:users,email`), `password` (`required|string|min:6`).
- **Response `201`:** Mengembalikan objek `data.user` (`id`, `name`, `email`, `role`).

### `POST /api/v1/login`
Autentikasi menggunakan email dan password untuk memperoleh cookie sesi Laravel.

- **Akses:** Publik
- **Request Body:**
```json
{
  "email": "operator@cargotrace.com",
  "password": "password"
}
```
- **Response `200`:** Mengembalikan `data.user`.
- **Response `401`:** `{ "success": false, "message": "Invalid email or password.", "data": null }`

### `GET /api/v1/user`
Mengambil profil user yang sedang login.

- **Akses:** Terautentikasi (`200` jika login, `401` jika belum login)

### `POST /api/v1/logout`
Mengakhiri sesi login aktif dan meregenerasi token sesi.

- **Akses:** Publik / Terautentikasi (`200`)

### `GET /api/v1/bootstrap`
Mengambil metadata awal untuk frontend: profil user aktif (atau `null`), daftar `locations`, `carriers`, `customers`, metrik ringkas database (`stats`), serta `delay_threshold_hours`.

- **Akses:** Publik (`200`)

---

## 3. Shipments & Lifecycle Workflow

### `GET /api/v1/shipments`
Menampilkan daftar pengiriman beserta relasi (`carrier`, `customer`, `buyer`, `originLocation`, `destinationLocation`, `currentLocation`, `items`, `exceptions`, `trackingEvents.location`) dengan pagination server-side.

- **Akses:** Publik
- **Query Parameters (opsional):**
  - `status`: `CREATED` | `BOOKED` | `CONFIRMED` | `PICKED_UP` | `IN_TRANSIT` | `DELIVERED` | `CANCELLED`
  - `customer_id`: Filter ID shipper
  - `buyer_id`: Filter ID buyer
  - `carrier_id`: Filter ID maskapai
  - `container_number`: Filter nomor kontainer persis
  - `is_delayed`: `true` | `false`
  - `search`: Pencarian pada `tracking_number`, `container_number`, `cargo_description`, nama carrier, lokasi asal, atau lokasi tujuan
  - `per_page`: Jumlah item per halaman (default `15`)

### `GET /api/v1/shipments/{tracking_number}`
Mengambil detail lengkap satu pengiriman berdasarkan `tracking_number`, `container_number`, atau `id`, disertai data cuaca pelabuhan langsung (`live_weather`), `escrow`, dan `delay_severity` (`NONE|LOW|MEDIUM|HIGH`).

- **Akses:** Publik/Terautentikasi. Bila login sebagai `customer`, server membatasi hanya untuk pengiriman milik customer terkait (`403` jika milik pihak lain).

### `GET /api/v1/public/track/{tracking_number}`
Endpoint pelacakan publik yang disanitasi (tanpa informasi sensitif customer/escrow) untuk form pencarian beranda.

- **Akses:** Publik (`200` / `404`)

### `POST /api/v1/shipments` (dan `POST /api/v1/admin/shipments`)
Mendaftarkan pengiriman baru beserta detail barang (`items`). `POST /api/v1/admin/shipments` dilindungi middleware `auth` + `role:admin,operator`.

- **Request Body:**
```json
{
  "tracking_number": "MRD-20261122-0001",
  "container_number": "CSQU3054383",
  "customer_id": 3,
  "buyer_id": 5,
  "carrier_id": 1,
  "origin_location_id": 1,
  "destination_location_id": 3,
  "cargo_description": "Kopi Arabika Gayo Grade 1 (500 karung)",
  "weight_kg": 18500,
  "volume_cbm": 64,
  "expected_departure": "2026-11-23 08:00:00",
  "expected_arrival": "2026-12-10 18:00:00",
  "notes": "Sertifikat phytosanitary terlampir",
  "items": [
    {
      "item_name": "Kopi Arabika Gayo Grade 1",
      "category": "Hasil Bumi & Kopi",
      "quantity": 500,
      "weight_kg": 18500,
      "condition": "BAIK & SEGEL UTUH",
      "notes": "GrainPro liners"
    }
  ]
}
```
- **Aturan Validasi (`StoreShipmentRequest`):**
  - `tracking_number`: Opsional (otomatis dibuat format `MRD-YYYYMMDD-XXXX` jika kosong), unik di `shipments`.
  - `container_number`: Opsional, divalidasi dengan **ISO 6346 check-digit** (`App\Rules\Iso6346Rule`, format 4 huruf + 7 angka).
  - `destination_location_id`: Harus berbeda dengan `origin_location_id` (`different:origin_location_id`).
  - `expected_arrival`: Harus setelah `expected_departure` (`after:expected_departure`).
  - `weight_kg`: Wajib, angka minimal `1`.
- **Response:** `201 Created` atau `422 Validation failed`.

### `PUT/PATCH /api/v1/shipments/{id}`
Memperbarui data pengiriman (menerima `id` atau `tracking_number`).

- **Akses:** Terautentikasi + Role `admin` atau `operator` (`401` / `403` jika tidak berhak)
- **Field Opsional (`UpdateShipmentRequest`):** `status` (`CREATED,BOOKED,PICKED_UP,IN_TRANSIT,DELIVERED,CANCELLED`), `carrier_id`, `destination_location_id`, `current_location_id`, `container_number` (`Iso6346Rule`), `cargo_description`, `weight_kg`, `volume_cbm`, `expected_departure`, `expected_arrival`, `notes`.

### `DELETE /api/v1/shipments/{id}`
Menghapus pengiriman dari database.

- **Akses:** Terautentikasi + Role `admin` atau `operator` (`200` / `401` / `403` / `404`)

### `POST /api/v1/shipments/{id}/confirm-shipper`
Shipper menyetujui rincian barang yang didaftarkan Operator. Jika status masih `CREATED` atau `BOOKED`, backend mentransisikan status ke `CONFIRMED`, membuat `TrackingEvent` bertipe `CONFIRMED_BY_SHIPPER`, dan mencatat `AuditLog`.

- **Akses:** Alur Shipper (`200` / `404`)

### `POST /api/v1/shipments/{id}/confirm-buyer`
Buyer mengonfirmasi penerimaan barang di pelabuhan tujuan. Backend memvalidasi transisi ke `DELIVERED` melalui `ShipmentLifecycleService`, memperbarui `current_location_id` ke tujuan, menambah `TrackingEvent` bertipe `DELIVERED`, dan mencatat `AuditLog`.

- **Akses:** Alur Buyer (`200` / `404` / `422` jika transisi status ilegal)

---

## 4. Tracking Timeline

### `GET /api/v1/shipments/{id}/tracking`
Mengambil riwayat event perjalanan secara kronologis beserta relasi `location` dan `creator`.

- **Akses:** Publik (`200` / `404`)

### `POST /api/v1/shipments/{id}/tracking`
Menambahkan checkpoint baru tanpa menimpa riwayat sebelumnya. Otomatis memperbarui `current_location_id`, menyinkronkan status (`DEPARTURE` → `IN_TRANSIT`, `DELIVERED` → `DELIVERED`), dan menulis `AuditLog`.

- **Akses:** Terautentikasi + Role `admin` atau `operator`
- **Request Body:**
```json
{
  "location_id": 2,
  "event_type": "IN_TRANSIT_CHECKPOINT",
  "event_time": "2026-11-26 10:30:00",
  "remarks": "Kapal transit di Port of Singapore untuk pengisian bahan bakar."
}
```
- **Enum `event_type`:** `DEPARTURE`, `ARRIVAL`, `IN_TRANSIT_CHECKPOINT`, `CUSTOMS_INSPECTION`, `OUT_FOR_DELIVERY`, `DELIVERED`.
- **Response:** `201 Created` / `422` (validasi atau transisi status tidak sah).

---

## 5. Exceptions & ETA Delay Engine

Perhitungan keterlambatan dijalankan deterministik di `EtaCalculationService`:
$$\text{calculated\_eta} = \text{expected\_arrival} + \sum \text{impact\_hours (exception berstatus OPEN)}$$
Flag `is_delayed` bernilai `true` bila selisih jam $\ge$ `CARGOTRACE_DELAY_THRESHOLD` (default `12` jam).

### `GET /api/v1/shipments/{id}/exceptions`
Daftar seluruh gangguan/exception pada suatu pengiriman.

- **Akses:** Publik (`200` / `404`)

### `POST /api/v1/shipments/{id}/exceptions`
Mencatat exception baru, menghitung ulang `calculated_eta` serta `is_delayed`, dan mencatat `AuditLog`.

- **Akses:** Terautentikasi + Role `admin` atau `operator`
- **Request Body:**
```json
{
  "tracking_event_id": null,
  "exception_type": "CUSTOMS_HOLD",
  "severity": "HIGH",
  "impact_hours": 24,
  "description": "Penahanan kontainer oleh bea cukai untuk pemeriksaan fisik dokumen ekspor."
}
```
- **Enum `exception_type`:** `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, `DOCUMENTATION_ISSUE` (serta kompatibilitas `VESSEL_BREAKDOWN`, `DOCUMENTATION_ERROR`).
- **Enum `severity`:** `LOW`, `MEDIUM`, `HIGH`, `CRITICAL`.
- **Validasi `impact_hours`:** `integer|min:1|max:720`.
- **Response:** `201 Created` dengan `data.exception` dan `data.shipment`.

### `POST /api/v1/exceptions/{id}/investigate`
Memindahkan status exception dari `OPEN` menjadi `INVESTIGATING` beserta catatan investigasi. (**Akses:** `admin` / `operator`)

### `POST /api/v1/exceptions/{id}/resolve`
Menandai exception sebagai `RESOLVED`, mencatat `resolved_at` dan `resolution_notes`, lalu menghitung ulang ETA pengiriman.

- **Akses:** Terautentikasi + Role `admin` atau `operator`
- **Request Body:**
```json
{
  "resolution_notes": "Dokumen phytosanitary terverifikasi, kontainer diizinkan melanjutkan pelayaran."
}
```
- **Response:** `200 OK`

---

## 6. Master Data, Weather & Audit Log

### `GET /api/v1/locations`
Daftar seluruh pelabuhan/hub UN/LOCODE. (**Akses:** Publik)

### `GET /api/v1/carriers`
Daftar maskapai pelayaran yang aktif (`is_active = true`). (**Akses:** Publik)

### `GET /api/v1/weather`
Mengambil kondisi cuaca maritim langsung dari **Open-Meteo API** (cache 10 menit, timeout 5 detik, dilengkapi fallback bila offline).

- **Akses:** Publik
- **Query Parameters:** `location_id` (opsional, mengambil koordinat pelabuhan dari DB) atau `lat` & `lng` (default `-6.1018, 106.8836`).

### `GET /api/v1/audit-logs`
Mengambil 60 catatan audit terbaru beserta aktor (`user`).

- **Akses:** Terautentikasi + Role `admin` atau `operator` (`401` / `403` / `200`)

---

## 7. Administrator Exclusive Endpoints (`/api/v1/admin/*`)

Seluruh endpoint di bawah ini wajib login dengan role **`admin`** (`401` jika belum login, `403` jika role selain `admin`).

| Method & URL | Kegunaan | Request Body / Validasi |
| :--- | :--- | :--- |
| `GET /api/v1/admin/overview` | Statistik eksekutif, `on_time_rate`, distribusi per status & per carrier | — |
| `GET /api/v1/admin/users` | Daftar seluruh akun pengguna + cabang | — |
| `POST /api/v1/admin/users` | Membuat akun baru (eksklusif Admin) | `name`, `email`, `password`, `role`, `location_id` (opsional) |
| `PUT/PATCH /api/v1/admin/users/{id}` | Mengedit profil, role, cabang & reset sandi | `name`, `email`, `role`, `location_id`, `password` |
| `PATCH /api/v1/admin/users/{id}/role` | Mengubah role pengguna | `role` (`admin\|operator\|customer\|surveyor`) |
| `GET /api/v1/admin/carriers` | Daftar seluruh maskapai + jumlah shipment | — |
| `POST /api/v1/admin/carriers` | Menambah maskapai baru | `code`, `name`, `transport_mode`, `contact_email`, `phone` |
| `PUT/PATCH /api/v1/admin/carriers/{id}` | Mengedit data maskapai | `code`, `name`, `transport_mode`, `contact_email`, `phone` |
| `PATCH /api/v1/admin/carriers/{id}/toggle` | Mengaktifkan / menonaktifkan maskapai | — |
| `POST /api/v1/admin/locations` | Menambah pelabuhan/hub baru ke bola bumi | `code`, `name`, `type`, `country`, `latitude`, `longitude` |
| `PUT/PATCH /api/v1/admin/locations/{id}` | Mengedit data & koordinat pelabuhan | `code`, `name`, `type`, `country`, `latitude`, `longitude` |

---

## 8. Notifikasi In-App, Dompet Virtual, Escrow & Demo Time Travel

| Method & URL | Kegunaan | Akses / Body |
| :--- | :--- | :--- |
| `GET /api/v1/notifications` | Daftar notifikasi user + `unread_count` | `auth` |
| `PATCH /api/v1/notifications/{id}/read` | Tandai satu notifikasi dibaca | `auth` |
| `POST /api/v1/notifications/read-all` | Tandai semua notifikasi dibaca | `auth` |
| `GET /api/v1/wallet` | Saldo dompet virtual, mutasi & daftar escrow | `auth` |
| `POST /api/v1/wallet/topup` | Top Up simulasi saldo Buyer | `amount_usd` (`min:10`) |
| `POST /api/v1/wallet/withdraw` | Penarikan simulasi Shipper ke rekening bank | `amount_usd`, `bank_destination` |
| `POST /api/v1/shipments/{id}/escrow/deposit` | Buyer setor dana ke Escrow (`HELD_IN_ESCROW`) | `auth` (Buyer pemilik) |
| `POST /api/v1/shipments/{id}/escrow/dispute` | Buyer ajukan sengketa Escrow | `reason` |
| `POST /api/v1/demo/time-travel` | Majukan waktu simulasi untuk tes delay | `auth` + `role:admin,operator` (`shipment_id`, `hours`) |
