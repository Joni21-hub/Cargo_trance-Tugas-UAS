# Meridian — CargoTrace Global Shipment Visibility & Tracking Platform

> Web Programming 2 — Final Project (UAS) · Group A scenario: **Southeast Asia → Europe**, delay threshold **12 hours**, primary exception **CUSTOMS_HOLD**, KPI **on-time delivery rate**.

Meridian is a Laravel application for tracking ocean freight shipments. It records each shipment's lifecycle and every tracking event, calculates ETA and delay with fixed deterministic rules, manages operational exceptions, handles simulated buyer-shipper escrow & virtual wallets, dispatches in-app notifications, and plots routes on an interactive 3D globe. Admins, Operators, Shippers, and Buyers each get their own dedicated workspace.

---

## 1. Problem

Logistics companies and their customers struggle with:

- Shipment information scattered across systems and hard to monitor.
- Customers who cannot see the latest status or travel history of their cargo.
- Delays and exceptions (customs holds, weather, congestion, damage, misrouting) that are not visible in one place.
- Operators who lack a focused workspace to prioritise follow-up actions by port branch.
- Low payment trust between cross-border Shippers and Buyers without shipment-tied escrow verification.

## 2. Solution & Features

| Area | What it does |
|---|---|
| **Shipment lifecycle** | Status transitions are validated in a backend state machine (`CREATED → BOOKED/CONFIRMED → PICKED_UP → IN_TRANSIT → DELIVERED`). Illegal transitions are rejected and `DELIVERED` is terminal. |
| **Tracking timeline** | Tracking events are append-only history (never overwritten) with historical `latitude`/`longitude` coordinates. Each event updates the shipment's current location and writes an audit log entry. |
| **ETA & delay engine** | `calculated_eta = expected_arrival + Σ(open exception impact_hours)`. A shipment is flagged *delayed* when delay is ≥ the configurable threshold (default 12 h), with `delay_severity` (`NONE`, `LOW`, `MEDIUM`, `HIGH`). |
| **Exception management** | Operators log `CUSTOMS_HOLD`, `WEATHER_DELAY`, `PORT_CONGESTION`, `DAMAGED`, `MISROUTED`, and `DOCUMENTATION_ISSUE` across workflow `OPEN → INVESTIGATING → RESOLVED`. Resolving recalculates the ETA automatically. |
| **Escrow & Virtual Wallet** | Simulated buyer-to-shipper escrow (`AWAITING_PAYMENT → HELD_IN_ESCROW → FROZEN_ON_EXCEPTION / RELEASED / DISPUTED`) with virtual wallet top-up, atomic settlement on buyer delivery confirmation, and shipper withdrawal ledger. |
| **In-App Notifications** | Persistent `notifications` table triggered on shipment registration, status changes, exceptions, resolutions, and escrow events, with unread count and mark-as-read APIs. |
| **ISO 6346 validation** | Container numbers are verified with the official ISO 6346 check-digit algorithm (`App\Rules\Iso6346Rule`). |
| **Role-based access (RBAC)** | Server-side `role` middleware and customer ownership scoping (`403 Forbidden` on unauthorized cross-customer access). Public registration is locked to `customer`; only Admin can provision/assign Operator accounts and branches. |
| **Interactive 3D globe** | A single MapLibre GL v5 globe with satellite (Esri World Imagery) and OpenStreetMap layers, seamless planet-to-port zoom, sea-lane routes, and live vessel/marker plotting. |
| **External API** | Live port weather from Open-Meteo through Laravel HTTP Client, with a 5 s timeout, caching, and a graceful fallback response. |
| **Dashboard & Audit Log** | Live database KPIs (total, in transit, pending, delivered, delayed, open exceptions, on-time rate) and immutable audit trail recording actor, action, entity, old/new values, and IP address. |
| **Public tracking** | `/track/{tracking_or_container_number}` and `/api/v1/public/track/{number}` work without logging in. |
| **Demo Time Travel** | Controlled operator simulation tool (`/api/v1/demo/time-travel`) to fast-forward active transit schedules and test delay detection live during demos (configurable via `CARGOTRACE_ENABLE_DEMO_TOOLS`). |

## 3. Tech Stack

- **Backend:** PHP 8.2+, Laravel 12, Eloquent ORM, Laravel HTTP Client
- **Database:** SQLite by default (zero-config), MySQL 8 compatible through `.env`
- **Frontend:** Blade, vanilla JavaScript + Fetch API, MapLibre GL JS v5 (3D globe), OpenStreetMap & Esri World Imagery tiles
- **External API:** Open-Meteo (no API key required)
- **Testing:** PHPUnit 11 (feature + unit tests)

## 4. Architecture & Technical Documentation

```
Browser ──► Routes + Middleware (auth, role) ──► Controllers ──► Service Layer ──► Eloquent Models ──► Database
                                                                     │
                                                                     └──► Open-Meteo API (HTTP Client)
```

Business logic lives in `app/Services`; controllers remain thin HTTP adapters:

| Service | Responsibility |
|---|---|
| `ShipmentLifecycleService` | Allowed status transitions and guarding against illegal ones |
| `TrackingEventService` | Recording events with coordinates, syncing location/status, audit log (`DB::transaction`) |
| `EtaCalculationService` | Deterministic ETA, delay flag, and `delay_severity` calculation |
| `ExceptionManagementService` | Logging, investigating, and resolving exceptions; freezing/unfreezing escrow & recalculating ETA |
| `EscrowService` | Virtual wallet top-up, escrow funding, atomic settlement on delivery, dispute handling, withdrawal |
| `NotificationService` | Creating and dispatching persistent in-app notifications per role/user |
| `WeatherService` | Open-Meteo integration with timeout, cache, and fallback |

Additional documentation in `docs/`:

- [Architecture Overview](docs/ARCHITECTURE.md)
- [Entity Relationship Diagram (ERD — 11 tables)](docs/ERD.md)
- [REST API Documentation](docs/API_DOCUMENTATION.md)
- [Postman Collection](docs/postman_collection.json)

## 5. Data Model (11 Tables)

`users` · `shipments` · `shipment_items` · `carriers` · `locations` · `tracking_events` · `shipment_exceptions` · `audit_logs` · `notifications` · `escrow_transactions` · `wallet_mutations`

Key relationships:

- `Shipment` **hasMany** `TrackingEvent`, `ShipmentException`, `ShipmentItem`, and **hasOne** `EscrowTransaction`
- `Shipment` **belongsTo** `User` (customer/shipper), `User` (buyer), `Carrier`, and `Location` (origin / destination / current)
- `User` **belongsTo** `Location` (operator branch) and **hasMany** `AppNotification`, `WalletMutation`

See [docs/ERD.md](docs/ERD.md) for the complete schema diagram and cardinalities.

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
| Logistics Operator | `operator@cargotrace.com` | `/operator` |
| Shipper (Pengirim) | `shipper@cargotrace.com` *(or `shipper@gmail.com`)* | `/shipper` |
| Buyer (Penerima) | `buyer@cargotrace.com` *(or `buyer@gmail.com`)* | `/buyer` |

### Seeded Master Data & Scenarios (`DatabaseSeeder`)

- 9 users, 6 international carriers (Maersk, MSC, Evergreen, ONE, CMA CGM, Hapag-Lloyd), 8 UN/LOCODE ports
- **Scenario 1 – On time:** Tanjung Priok → Rotterdam, `IN_TRANSIT`, no delay
- **Scenario 2 – Customs hold:** Singapore → Hamburg, 24 h `CUSTOMS_HOLD`, flagged delayed
- **Scenario 3 – Delivered:** full six-milestone history with settled escrow
- **Scenario 4 – Created:** awaiting shipper confirmation

## 7. REST API (prefix `/api/v1`)

| Method | Endpoint | Access |
|---|---|---|
| POST | `/register`, `/login`, `/logout` | Public (register locked to `customer`) |
| GET | `/user`, `/bootstrap` | Session |
| GET | `/public/track/{number}` | Public |
| GET | `/shipments` (search, date/port/status filters, sorting, pagination) | Session (customer-scoped) / Public summary |
| GET | `/shipments/{tracking\|container\|id}` | Scoped by ownership/role |
| POST | `/shipments` | Operator, Admin |
| PUT/PATCH, DELETE | `/shipments/{id}` | Operator, Admin |
| GET / POST | `/shipments/{id}/tracking` | Scoped / Operator, Admin |
| GET / POST | `/shipments/{id}/exceptions` | Scoped / Operator, Admin |
| POST | `/exceptions/{id}/investigate`, `/exceptions/{id}/resolve` | Operator, Admin |
| POST | `/shipments/{id}/confirm-shipper`, `/confirm-buyer` | Shipper / Buyer owner |
| GET / POST | `/wallet`, `/wallet/top-up`, `/wallet/withdraw` | Authenticated user |
| POST | `/shipments/{id}/escrow/fund`, `/escrow/dispute` | Buyer owner |
| GET / POST | `/notifications`, `/notifications/{id}/read`, `/notifications/read-all` | Authenticated user |
| GET | `/audit-logs` | Operator, Admin |
| POST | `/demo/time-travel` | Operator, Admin (when enabled) |
| GET/POST/PATCH | `/admin/overview`, `/admin/users`, `/admin/carriers`, `/admin/locations` | Admin only |
| GET | `/weather?location_id=` | Public |

Status codes: `200/201` success, `401` unauthenticated, `403` forbidden, `404` not found, `422` validation error. All responses share one consistent JSON envelope: `{ success, message, data }`. See [docs/API_DOCUMENTATION.md](docs/API_DOCUMENTATION.md) for full request/response payloads.

## 8. Running Tests

```bash
php artisan test
```

Current result: **47 tests, 317 assertions, all passing.** Coverage includes:

- Authentication: valid/invalid login, logout, registration (including anti-privilege-escalation check)
- RBAC & Data Isolation: guest → `401`, customer → `403` on operator/admin endpoints and other customers' shipments
- Shipment CRUD, validation, ISO 6346 check digit
- Illegal status transition (`CREATED → DELIVERED`) rejected
- Tracking events: coordinate history, location sync, auto status transition, audit log
- ETA/delay rule: `< 12 h` not delayed, `= 12 h` delayed, accumulation, resolution, `delay_severity`
- Full Escrow, Virtual Wallet, Exception Investigation (`OPEN → INVESTIGATING → RESOLVED`), Notifications, Admin Provisioning, and Demo Time Travel flows
- End-to-end workflow from cargo registration to buyer delivery & escrow settlement

## 9. Configuration

| Key | Default | Description |
|---|---|---|
| `CARGOTRACE_DELAY_THRESHOLD` | `12` | Hours of ETA slip before a shipment is flagged delayed |
| `CARGOTRACE_ENABLE_DEMO_TOOLS` | `true` | Enables/disables the demo time-travel endpoint |

## 10. Known Limitations & Notes

- Escrow and virtual wallet flows are internal simulations (no live banking or payment gateway API integration).
- API authentication uses Laravel stateful session cookies; stateless Sanctum bearer tokens can be added if external mobile/third-party clients are introduced.
- Map visualization uses MapLibre GL JS v5 (with OpenStreetMap & Esri tiles) rather than 2D Leaflet.js to support 3D globe projection.
- Live deployment URL: _to be added after cloud deployment_.

## 11. Attribution

- [Laravel](https://laravel.com) — MIT
- [MapLibre GL JS](https://maplibre.org) — BSD-3-Clause
- Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors
- Satellite imagery © Esri, Maxar, Earthstar Geographics
- Weather data © [Open-Meteo](https://open-meteo.com) — CC BY 4.0
- Fonts: Plus Jakarta Sans, JetBrains Mono (Google Fonts, OFL)
- Parts of this code were produced with AI assistance and reviewed by the team.
