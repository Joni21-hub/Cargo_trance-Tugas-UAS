# Meridian — CargoTrace Global Export Visibility, Physical Verification & Escrow Platform

> Web Programming 2 — Final Project (UAS) · Multimodal Nusantara export logistics (**5 Major Indonesian Islands → Global & USA/Europe Hubs**), delay threshold **12 hours**, primary exception **CUSTOMS_HOLD**, KPI **on-time delivery rate**.

Meridian is a Laravel 12 enterprise web application for global export cargo visibility, anti-fraud physical inspection, non-marketplace Nusantara coffee discovery, and simulated buyer–shipper escrow (*Rekening Jaminan*). It separates roles strictly across **Administrator (`/admin`)**, **Branch Operator (`/operator`)**, **Shipper / Pengirim (`/shipper`)**, and **Buyer / Pembeli (`/buyer`)**, features a Clean Daylight Bahasa Indonesia UI responsive for Desktop & Mobile, and plots multimodal land–sea–air cargo movement on an interactive 3D globe.

---

## 1. Problem

Cross-border agricultural/coffee exporters and international buyers face recurring operational gaps:

- **Cargo fraud risk:** Self-reported manifests without neutral physical verification lead to weight, quality, or seal discrepancies.
- **Fragmented shipment visibility:** Shippers and Buyers cannot inspect real-time multimodal routes (land truck → sea vessel / air freighter) or deterministic delay status in one place.
- **Payment trust barrier:** Buyers hesitate to wire funds directly to overseas suppliers without milestone-linked escrow protection.
- **Unstructured discovery & support:** Exporters across Indonesia's 5 major islands lack a verified identity directory (non-marketplace) with direct buyer–shipper–operator chat and admin escalation.

## 2. Solution & Features

| Area | What it does |
|---|---|
| **Anti-fraud physical verification** | Shippers do **not** self-create official manifests. Clicking **Buat Pengiriman** dispatches a **Branch Operator** (`pickup_requests`) with our internal branch land fleet (`MRDTRK`) to physically verify cargo (5-point checklist + seal number) before recording the official shipment manifest, which the Shipper then reviews and approves. |
| **Nusantara Coffee Directory (Non-Marketplace)** | Displays verified Indonesian coffee origins across all 5 islands (`Sumatra`, `Jawa`, `Kalimantan`, `Sulawesi`, `Papua`) with **zero price fields**. Buyers inspect verified Shipper profiles and negotiate terms directly via in-app Chat. |
| **Multimodal 5-Island + Global Network** | 17 UN/LOCODE ports & cargo airports: exactly 1 seaport + 1 cargo airport per major Indonesian island (`IDBLW`/`IDKNO`, `IDTPR`/`IDCGK`, `IDPNK`/`WIOO`, `IDMAK`/`IDUPG`, `IDDJJ`/`WAJJ`) plus global & US hubs (`USLAX`, `USJFK`, `SGSIN`, `NLRTM`, `DEHAM`, `CNSHA`, `JPTYO`). Target arrival `≤ 7` days auto-recommends **AIR** (cargo aircraft); `> 7` days selects **SEA** (ocean vessel). |
| **Shipment lifecycle** | Strict backend state machine (`CREATED → BOOKED/CONFIRMED → PICKED_UP → IN_TRANSIT → DELIVERED`). Illegal transitions are rejected (`422`) and `DELIVERED` is terminal. |
| **Tracking timeline** | Append-only history with historical `latitude`/`longitude` coordinates, automatic location/status synchronization, and full audit logging. |
| **ETA & delay engine** | `calculated_eta = expected_arrival + Σ(open exception impact_hours)`. Flagged *delayed* when slip ≥ configurable threshold (default `12` hours) with `delay_severity` (`NONE`, `LOW`, `MEDIUM`, `HIGH`). |
| **Exception management** | Operators log `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, and `DOCUMENTATION_ISSUE` across `OPEN → INVESTIGATING → RESOLVED`, automatically freezing/unfreezing escrow and recalculating ETA. |
| **Escrow & Virtual Wallet** | Simulated buyer-to-shipper escrow (`AWAITING_PAYMENT → HELD_IN_ESCROW → FROZEN_ON_EXCEPTION / RELEASED / DISPUTED`), virtual wallet top-up, atomic payout on buyer delivery confirmation, and shipper bank withdrawal ledger. |
| **Digital Release Document** | Generates printable/PDF **Telex Release (e-B/L)**, **Air Waybill**, or physical courier tracking record (`POST /api/v1/shipments/{id}/release-document`) for customs pickup. |
| **Direct Chat & Bantuan Admin** | Real-time thread messaging (`Buyer ↔ Shipper ↔ Operator`), public & authenticated **Bantuan Admin** support tickets (`support_tickets`), and Admin Inbox replies. |
| **Verified Profiles & RBAC** | Full identity profiles (Company, Phone/WA, NIB/NPWP, Address) with Admin verification badge (`PATCH /admin/users/{id}/verify`), server-side `role` middleware, and strict cross-customer ownership isolation (`403 Forbidden`). |
| **Interactive 3D Multimodal Map** | MapLibre GL JS v5 3D globe with Daylight OpenStreetMap & Esri Satellite layers, animated Truck / Ship / Airplane markers, and **`Besok (+1 Hari)`** time-travel simulation synced with live vehicle progression. |
| **ISO 6346 & Open-Meteo Weather** | Official ISO 6346 container check-digit validation (`App\Rules\Iso6346Rule`) and cached live port weather via Open-Meteo HTTP Client with 5 s timeout & offline fallback. |
| **Public Tracking & Audit Log** | Sanitized public tracking (`/track/{number}` & `/api/v1/public/track/{number}`) and immutable `audit_logs` capturing actor, action, before/after JSON snapshots, and IP address. |

## 3. Tech Stack

- **Backend:** PHP 8.2+, Laravel 12, Eloquent ORM, Laravel HTTP Client
- **Database:** SQLite by default (zero-config), MySQL 8 compatible via `.env`
- **Frontend:** Blade partials, Clean Daylight Enterprise CSS, vanilla JavaScript + Fetch API, MapLibre GL JS v5 (3D globe), OpenStreetMap & Esri World Imagery tiles
- **External API:** Open-Meteo (no API key required)
- **Testing:** PHPUnit 11 (feature + unit test suites)

## 4. Architecture & Technical Documentation

```
Browser ──► Routes + Middleware (auth, role) ──► Controllers ──► Service Layer ──► Eloquent Models ──► Database
                                                                     │
                                                                     └──► Open-Meteo API (HTTP Client)
```

Core business logic lives in `app/Services`; controllers remain thin HTTP adapters:

| Service | Responsibility |
|---|---|
| `ShipmentLifecycleService` | Allowed status transitions and guarding against illegal ones |
| `TrackingEventService` | Recording events with coordinates, syncing location/status, audit log (`DB::transaction`) |
| `EtaCalculationService` | Deterministic ETA, delay flag, and `delay_severity` calculation |
| `ExceptionManagementService` | Logging, investigating, and resolving exceptions; freezing/unfreezing escrow & recalculating ETA |
| `EscrowService` | Virtual wallet top-up, escrow deposit, atomic settlement on delivery, dispute handling, withdrawal |
| `NotificationService` | Creating and dispatching persistent in-app notifications per role/user |
| `WeatherService` | Open-Meteo integration with timeout, cache, and fallback |

Additional documentation in `docs/`:

- [Architecture Overview](docs/ARCHITECTURE.md)
- [Entity Relationship Diagram (ERD — 15 tables)](docs/ERD.md)
- [REST API Documentation](docs/API_DOCUMENTATION.md)
- [Postman Collection](docs/postman_collection.json)

## 5. Data Model (15 Core Tables)

`users` · `shipments` · `shipment_items` · `carriers` · `locations` · `tracking_events` · `shipment_exceptions` · `audit_logs` · `notifications` · `escrow_transactions` · `wallet_mutations` · `export_products` · `pickup_requests` · `chat_messages` · `support_tickets`

Key relationships:

- `Shipment` **hasMany** `TrackingEvent`, `ShipmentException`, `ShipmentItem`, `ChatMessage`, and **hasOne** `EscrowTransaction`, `PickupRequest`
- `Shipment` **belongsTo** `User` (shipper), `User` (buyer), `Carrier`, and `Location` (origin / destination / current)
- `User` **belongsTo** `Location` (operator island branch) and **hasMany** `ExportProduct`, `PickupRequest`, `ChatMessage`, `SupportTicket`, `AppNotification`, `WalletMutation`

See [docs/ERD.md](docs/ERD.md) for the complete schema diagram and data dictionary.

## 6. Setup

```bash
git clone https://github.com/Joni21-hub/Cargo_trance-Tugas-UAS.git
cd Cargo_trance-Tugas-UAS
composer install
cp .env.example .env
php artisan key:generate

# SQLite (default)
touch database/database.sqlite      # Windows: type nul > database\database.sqlite

# Migrate and seed master data & demo scenarios
php artisan migrate:fresh --seed

php artisan serve
```

Open <http://127.0.0.1:8000>.

To use **MySQL 8**, set `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`, then run `php artisan migrate --seed`.

### Seeded Accounts (password: `password`)

| Role | Email | Workspace |
|---|---|---|
| Administrator | `admin@cargotrace.com` | `/admin` |
| Branch Operator (Jawa — `IDTPR`) | `operator@cargotrace.com` | `/operator` |
| Island Operators (Sumatra / Kalimantan / Sulawesi / Papua) | `operator.sumatra@cargotrace.com`, `operator.kalimantan@cargotrace.com`, `operator.sulawesi@cargotrace.com`, `operator.papua@cargotrace.com` | `/operator` |
| Shipper (Pengirim) | `shipper@cargotrace.com` *(alias: `shipper@gmail.com`)* | `/shipper` |
| Buyer (Penerima) | `buyer@cargotrace.com` *(alias: `buyer@gmail.com`)* | `/buyer` |

### Seeded Master Data (`DatabaseSeeder`)

- **16 verified users** (Admin, 5 Indonesian island operators, Nusantara coffee exporters, international buyers)
- **17 UN/LOCODE hubs** (5 major Indonesian islands × 1 seaport + 1 cargo airport + 7 international hubs including `USLAX` & `USJFK`)
- **11 carriers** (6 international sea lines, 2 national sea lines, 2 air cargo lines, 1 internal branch land fleet `MRDTRK`)
- **9 Nusantara coffee directory entries** across Sumatra, Jawa, Kalimantan, Sulawesi, and Papua (non-marketplace, zero prices)
- **4 demonstration shipments** covering on-time transit, `CUSTOMS_HOLD` (24 h delay), delivered + settled escrow, and awaiting shipper confirmation

## 7. REST API (prefix `/api/v1`)

| Method | Endpoint | Access |
|---|---|---|
| POST | `/register`, `/login`, `/logout` | Public (register locked to `customer`) |
| GET | `/user`, `/bootstrap` | Public / Session |
| GET / PUT | `/profile`, `/users/{id}/public-profile`, `/operators` | Auth / Public directory |
| GET | `/public/track/{number}` | Public |
| GET / POST / PUT / DELETE | `/export-products`, `/export-products/{id}` | Public read / Auth Shipper & Admin write |
| GET / POST / PATCH | `/pickup-requests`, `/pickup-requests/{id}/dispatch`, `/pickup-requests/{id}/cancel` | Auth Shipper / Operator & Admin |
| GET | `/shipments` (search, date/port/branch/status filters, sorting, pagination) | Session (customer-scoped) / Public |
| GET | `/shipments/{tracking\|container\|id}` | Scoped by ownership/role |
| POST / PUT / DELETE | `/shipments`, `/shipments/{id}` | Operator, Admin |
| POST | `/shipments/{id}/release-document` | Operator, Admin, or Shipper owner |
| GET / POST | `/shipments/{id}/tracking` | Scoped / Operator, Admin |
| GET / POST | `/shipments/{id}/exceptions` | Scoped / Operator, Admin |
| POST | `/exceptions/{id}/investigate`, `/exceptions/{id}/resolve` | Operator, Admin |
| POST | `/shipments/{id}/confirm-shipper`, `/confirm-buyer` | Shipper / Buyer owner |
| GET / POST | `/wallet`, `/wallet/topup`, `/wallet/withdraw` | Authenticated user |
| POST | `/shipments/{id}/escrow/deposit`, `/shipments/{id}/escrow/dispute` | Buyer owner |
| GET / POST | `/chats/threads`, `/chats/messages` | Authenticated user |
| GET / POST | `/support-tickets` | Public & Auth create / Auth read |
| GET / PATCH / POST | `/notifications`, `/notifications/{id}/read`, `/notifications/read-all` | Authenticated user |
| GET | `/audit-logs` | Operator, Admin |
| POST | `/demo/time-travel` | Operator, Admin (when enabled) |
| GET/POST/PUT/PATCH | `/admin/overview`, `/admin/users`, `/admin/users/{id}/verify`, `/admin/carriers`, `/admin/locations`, `/admin/support-tickets/{id}/reply` | Admin only |
| GET | `/weather?location_id=` | Public |

Status codes: `200/201` success, `401` unauthenticated, `403` forbidden, `404` not found, `422` validation error. All responses use the consistent JSON envelope `{ success, message, data }`. See [docs/API_DOCUMENTATION.md](docs/API_DOCUMENTATION.md) for complete payloads.

## 8. Running Tests

```bash
php artisan test
```

Current result: **52 tests, 395 assertions, all passing.** Coverage includes:

- Authentication: valid/invalid login, email aliases, logout, registration (with anti-privilege-escalation check)
- RBAC & Data Isolation: guest → `401`, customer → `403` on operator/admin endpoints and other customers' shipments
- Shipment CRUD, validation, ISO 6346 check digit, illegal status transition (`CREATED → DELIVERED`) rejection
- Tracking events: coordinate history, location sync, auto status transition, audit log
- ETA/delay engine: `< 12 h` not delayed, `= 12 h` delayed, accumulation, resolution, `delay_severity`
- Escrow, Virtual Wallet (`topup`, `deposit`, `withdraw`, `dispute`), Exception Investigation (`OPEN → INVESTIGATING → RESOLVED`), Notifications, Admin Provisioning, and Demo Time Travel
- Enterprise modules: Verified Profiles, Nusantara Coffee Directory (5 islands, no prices), Shipper Pickup Call (`≤ 7` days auto-AIR, truck dispatch, conversion to official manifest with 5-point checklist), Direct Chat, Bantuan Admin Tickets & Replies, and Digital Release Documents

## 9. Configuration

| Key | Default | Description |
|---|---|---|
| `CARGOTRACE_DELAY_THRESHOLD` | `12` | Hours of ETA slip before a shipment is flagged delayed |
| `CARGOTRACE_ENABLE_DEMO_TOOLS` | `true` | Enables/disables the demo time-travel endpoint (`Besok +1 Hari`) |

## 10. Known Limitations & Notes

- Escrow and virtual wallet flows are internal simulations (no live banking or payment gateway API integration).
- API authentication uses Laravel stateful session cookies; stateless Sanctum bearer tokens can be added for external mobile clients.
- Map visualization uses MapLibre GL JS v5 (OpenStreetMap & Esri tiles) to support 3D globe projection and animated multimodal vehicle icons.
- Live deployment URL: _to be added after cloud deployment_.

## 11. Attribution

- [Laravel](https://laravel.com) — MIT
- [MapLibre GL JS](https://maplibre.org) — BSD-3-Clause
- Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors
- Satellite imagery © Esri, Maxar, Earthstar Geographics
- Weather data © [Open-Meteo](https://open-meteo.com) — CC BY 4.0
- Fonts: Plus Jakarta Sans, JetBrains Mono (Google Fonts, OFL)
- Parts of this code were produced with AI assistance and reviewed by the team.
