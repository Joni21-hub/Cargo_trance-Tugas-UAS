<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use App\Services\EscrowService;
use App\Services\EtaCalculationService;
use App\Services\NotificationService;
use App\Services\ShipmentLifecycleService;
use App\Services\WeatherService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends BaseApiController
{
    public function __construct(
        protected EtaCalculationService $etaService,
        protected WeatherService $weatherService,
        protected ShipmentLifecycleService $lifecycleService,
        protected EscrowService $escrowService,
        protected NotificationService $notificationService
    ) {}

    /**
     * GET /api/v1/shipments
     * List shipments with server-side RBAC scoping, search, filters, sorting, and pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Shipment::with([
            'carrier',
            'customer',
            'buyer',
            'originLocation',
            'destinationLocation',
            'currentLocation',
            'items',
            'exceptions',
            'escrow',
            'trackingEvents.location',
        ]);

        // Server-side customer data isolation (Slide 5 & 13)
        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer') {
            $query->where(function ($q) use ($authUser) {
                $q->where('customer_id', $authUser->id)
                  ->orWhere('buyer_id', $authUser->id);
            });
        } else {
            if ($request->filled('customer_id')) {
                $query->where('customer_id', $request->query('customer_id'));
            }
            if ($request->filled('buyer_id')) {
                $query->where('buyer_id', $request->query('buyer_id'));
            }
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('carrier_id')) {
            $query->where('carrier_id', $request->query('carrier_id'));
        }

        if ($request->filled('origin_location_id')) {
            $query->where('origin_location_id', $request->query('origin_location_id'));
        }

        if ($request->filled('destination_location_id')) {
            $query->where('destination_location_id', $request->query('destination_location_id'));
        }

        if ($request->filled('branch_location_id')) {
            $branchId = (int) $request->query('branch_location_id');
            $query->where(function ($q) use ($branchId) {
                $q->where('origin_location_id', $branchId)
                  ->orWhere('destination_location_id', $branchId)
                  ->orWhere('current_location_id', $branchId);
            });
        }

        if ($request->filled('container_number')) {
            $query->where('container_number', $request->query('container_number'));
        }

        if ($request->has('is_delayed')) {
            $query->where('is_delayed', filter_var($request->query('is_delayed'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('date_from')) {
            $query->whereDate('expected_departure', '>=', $request->query('date_from'));
        }

        if ($request->filled('date_to')) {
            $query->whereDate('expected_arrival', '<=', $request->query('date_to'));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('container_number', 'like', "%{$search}%")
                  ->orWhere('cargo_description', 'like', "%{$search}%")
                  ->orWhereHas('carrier', fn($c) => $c->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('originLocation', fn($l) => $l->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                  ->orWhereHas('destinationLocation', fn($l) => $l->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"));
            });
        }

        // Server-side sorting (allowlisted columns only)
        $allowedSorts = ['created_at', 'expected_departure', 'expected_arrival', 'calculated_eta', 'status', 'weight_kg'];
        $sortBy = in_array($request->query('sort_by'), $allowedSorts, true) ? $request->query('sort_by') : 'created_at';
        $sortDir = strtolower((string) $request->query('sort_dir')) === 'asc' ? 'asc' : 'desc';

        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $shipments = $query->orderBy($sortBy, $sortDir)->paginate($perPage);

        return $this->sendResponse($shipments, 'Shipments retrieved successfully');
    }

    /**
     * GET /api/v1/shipments/{tracking_number}
     * Retrieve single shipment by tracking number, container number, or ID with route, escrow, and live weather.
     */
    public function show(Request $request, string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::with([
            'carrier',
            'customer',
            'buyer',
            'originLocation',
            'destinationLocation',
            'currentLocation',
            'items',
            'escrow',
            'trackingEvents.location',
            'trackingEvents.creator',
            'exceptions',
        ])
        ->where('tracking_number', $trackingNumber)
        ->orWhere('container_number', $trackingNumber)
        ->orWhere('id', $trackingNumber)
        ->first();

        if (!$shipment) {
            return $this->sendError("Shipment with tracking number or ID '{$trackingNumber}' not found", null, 404);
        }

        // Enforce server-side ownership if authenticated as a customer
        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer') {
            if ((int) $shipment->customer_id !== (int) $authUser->id && (int) $shipment->buyer_id !== (int) $authUser->id) {
                return $this->sendError('Forbidden: Anda tidak memiliki akses ke pengiriman milik pihak lain.', null, 403);
            }
        }

        $weatherData = $this->resolveWeatherData($shipment);

        $result = $shipment->toArray();
        $result['live_weather'] = $weatherData;

        return $this->sendResponse($result, 'Shipment details retrieved successfully');
    }

    /**
     * GET /api/v1/public/track/{tracking_number}
     * Sanitized public tracking endpoint without private customer/escrow details.
     */
    public function publicTrack(string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::with([
            'carrier:id,code,name,transport_mode',
            'originLocation',
            'destinationLocation',
            'currentLocation',
            'trackingEvents.location',
        ])
        ->where('tracking_number', $trackingNumber)
        ->orWhere('container_number', $trackingNumber)
        ->first();

        if (!$shipment) {
            return $this->sendError("Tracking number '{$trackingNumber}' tidak ditemukan.", null, 404);
        }

        return $this->sendResponse([
            'id' => $shipment->id,
            'tracking_number' => $shipment->tracking_number,
            'container_number' => $shipment->container_number,
            'status' => $shipment->status,
            'transport_mode' => $shipment->transport_mode ?? ($shipment->carrier?->transport_mode ?? 'SEA'),
            'pickup_address' => $shipment->pickup_address,
            'pickup_latitude' => $shipment->pickup_latitude,
            'pickup_longitude' => $shipment->pickup_longitude,
            'seal_number' => $shipment->seal_number,
            'verification_checklist' => $shipment->verification_checklist,
            'travel_progress_pct' => $shipment->travel_progress_pct,
            'simulated_days_elapsed' => $shipment->simulated_days_elapsed ?? 0,
            'cargo_description' => $shipment->cargo_description,
            'weight_kg' => $shipment->weight_kg,
            'volume_cbm' => $shipment->volume_cbm,
            'expected_departure' => $shipment->expected_departure,
            'expected_arrival' => $shipment->expected_arrival,
            'calculated_eta' => $shipment->calculated_eta,
            'is_delayed' => $shipment->is_delayed,
            'delay_hours' => $shipment->delay_hours,
            'delay_severity' => $shipment->delay_severity,
            'carrier' => $shipment->carrier,
            'origin_location' => $shipment->originLocation,
            'destination_location' => $shipment->destinationLocation,
            'current_location' => $shipment->currentLocation,
            'tracking_events' => $shipment->trackingEvents,
            'live_weather' => $this->resolveWeatherData($shipment),
        ], 'Public tracking details retrieved successfully');
    }

    /**
     * POST /api/v1/shipments
     * Operator registers a new cargo shipment with detail barang & optional Escrow contract.
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['tracking_number'])) {
            $data['tracking_number'] = 'MRD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        }

        if (!empty($data['container_number'])) {
            $data['container_number'] = strtoupper(trim(str_replace([' ', '-'], '', $data['container_number'])));
        }

        $data['current_location_id'] = $data['origin_location_id'];
        $data['calculated_eta'] = $data['expected_arrival'];
        $data['status'] = $data['status'] ?? 'CREATED';
        $data['is_delayed'] = false;
        $data['delay_hours'] = 0;

        $itemsData = $data['items'] ?? null;
        $contractAmountUsd = (float) ($data['contract_amount_usd'] ?? 0);
        $pickupRequestId = $data['pickup_request_id'] ?? null;
        unset($data['items'], $data['contract_amount_usd'], $data['pickup_request_id']);

        if (empty($data['transport_mode'])) {
            $carrier = Carrier::find($data['carrier_id']);
            $data['transport_mode'] = $carrier?->transport_mode ?: 'SEA';
        }

        if (!empty($data['verification_checklist']) && is_array($data['verification_checklist'])) {
            $data['verification_checklist']['verified_by'] = auth()->user()?->name ?? 'Operator Cabang';
            $data['verification_checklist']['verified_at'] = now()->toDateTimeString();
        }

        $shipment = Shipment::create($data);

        if ($pickupRequestId) {
            PickupRequest::where('id', $pickupRequestId)->update([
                'status' => 'CONVERTED_TO_SHIPMENT',
                'shipment_id' => $shipment->id,
                'assigned_operator_id' => auth()->id(),
            ]);
        }

        if (!empty($itemsData) && is_array($itemsData)) {
            foreach ($itemsData as $item) {
                $shipment->items()->create([
                    'item_name' => $item['item_name'] ?? 'Barang Kargo',
                    'category' => $item['category'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'weight_kg' => $item['weight_kg'] ?? 0,
                    'condition' => $item['condition'] ?? 'BAIK',
                    'notes' => $item['notes'] ?? null,
                ]);
            }
        } else {
            $shipment->items()->create([
                'item_name' => $shipment->cargo_description,
                'quantity' => 1,
                'weight_kg' => $shipment->weight_kg,
                'condition' => 'BAIK & TERSEGEL',
            ]);
        }

        // Initialize simulated Escrow contract if contract amount provided
        if ($contractAmountUsd > 0) {
            $this->escrowService->initializeForShipment($shipment, $contractAmountUsd);
        }

        AuditLog::create([
            'user_id' => auth()->id() ?? $shipment->customer_id,
            'action' => 'CREATE_SHIPMENT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => null,
            'new_values' => [
                'tracking_number' => $shipment->tracking_number,
                'container_number' => $shipment->container_number,
                'status' => $shipment->status,
                'contract_amount_usd' => $contractAmountUsd,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $this->notificationService->notify(
            $shipment->customer_id,
            $shipment->id,
            'SHIPMENT_CREATED',
            "Kargo Baru Menunggu Konfirmasi ({$shipment->tracking_number})",
            "Operator mendaftarkan {$shipment->cargo_description}. Silakan periksa dan setujui pengiriman."
        );

        if ($shipment->buyer_id && $contractAmountUsd > 0) {
            $this->notificationService->notify(
                $shipment->buyer_id,
                $shipment->id,
                'ESCROW_UPDATE',
                "Tagihan Escrow Tersedia ({$shipment->tracking_number})",
                'Kontrak dagang senilai $' . number_format($contractAmountUsd, 2) . ' USD siap disetor ke Escrow Meridian.'
            );
        }

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'currentLocation', 'items', 'escrow', 'exceptions', 'trackingEvents.location']),
            'Shipment created successfully by Operator',
            201
        );
    }

    /**
     * PUT/PATCH /api/v1/shipments/{id}
     * Update an existing shipment (Operator & Admin).
     */
    public function update(UpdateShipmentRequest $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $data = $request->validated();
        $oldValues = $shipment->only(array_keys($data));

        // Guard status transition if status field is included
        if (isset($data['status']) && $data['status'] !== $shipment->status) {
            try {
                $this->lifecycleService->transition($shipment, $data['status']);
            } catch (DomainException $e) {
                return $this->sendError($e->getMessage(), null, 422);
            }
            unset($data['status']);
        }

        $shipment->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'UPDATE_SHIPMENT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => $oldValues,
            'new_values' => $shipment->only(array_keys($oldValues)),
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $shipment->fresh(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'currentLocation', 'items', 'escrow']),
            'Shipment updated successfully'
        );
    }

    /**
     * DELETE /api/v1/shipments/{id}
     */
    public function destroy(string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'DELETE_SHIPMENT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['tracking_number' => $shipment->tracking_number, 'status' => $shipment->status],
            'new_values' => null,
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $shipment->delete();

        return $this->sendResponse(null, 'Shipment deleted successfully');
    }

    /**
     * GET /api/v1/shipments/{id}/tracking
     */
    public function tracking(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer') {
            if ((int) $shipment->customer_id !== (int) $authUser->id && (int) $shipment->buyer_id !== (int) $authUser->id) {
                return $this->sendError('Forbidden: Tidak berhak mengakses riwayat pengiriman ini.', null, 403);
            }
        }

        $events = $shipment->trackingEvents()->with(['location', 'creator'])->get();

        return $this->sendResponse($events, 'Tracking history retrieved successfully');
    }

    /**
     * POST /api/v1/shipments/{id}/confirm-shipper
     */
    public function confirmByShipper(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer' && (int) $shipment->customer_id !== (int) $authUser->id) {
            return $this->sendError('Forbidden: Hanya Shipper pemilik kargo ini yang berhak mengonfirmasi.', null, 403);
        }

        $oldStatus = $shipment->status;

        if ($shipment->status === 'CREATED' || $shipment->status === 'BOOKED') {
            $this->lifecycleService->transition($shipment, 'CONFIRMED');
        }

        $originLoc = $shipment->originLocation;

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $shipment->origin_location_id,
            'latitude' => $originLoc?->latitude,
            'longitude' => $originLoc?->longitude,
            'event_type' => 'CONFIRMED_BY_SHIPPER',
            'event_time' => now(),
            'remarks' => 'Barang & dokumen ekspor disetujui serta dikonfirmasi oleh Shipper.',
            'created_by' => auth()->id() ?? $shipment->customer_id,
        ]);

        AuditLog::create([
            'user_id' => auth()->id() ?? $shipment->customer_id,
            'action' => 'SHIPPER_CONFIRM_CARGO',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $shipment->status],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $this->notificationService->notify(
            $shipment->buyer_id,
            $shipment->id,
            'SHIPMENT_CONFIRMED',
            "Pengiriman Disetujui Shipper ({$shipment->tracking_number})",
            "Shipper telah mengonfirmasi kargo {$shipment->cargo_description} untuk diberangkatkan."
        );

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'items', 'escrow']),
            'Kargo berhasil dikonfirmasi dan disetujui oleh Shipper.'
        );
    }

    /**
     * POST /api/v1/shipments/{id}/confirm-buyer
     * Buyer confirms receipt -> transitions to DELIVERED and automatically releases Escrow if held!
     */
    public function confirmReceiptByBuyer(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $authUser = $request->user();
        if ($authUser && $authUser->role === 'customer' && (int) $shipment->buyer_id !== (int) $authUser->id) {
            return $this->sendError('Forbidden: Hanya Buyer penerima pengiriman ini yang dapat mengonfirmasi tiba.', null, 403);
        }

        $oldStatus = $shipment->status;

        try {
            $this->lifecycleService->transition($shipment, 'DELIVERED');
        } catch (DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        }

        $shipment->current_location_id = $shipment->destination_location_id;
        $shipment->save();

        $destLoc = $shipment->destinationLocation;

        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $shipment->destination_location_id,
            'latitude' => $destLoc?->latitude,
            'longitude' => $destLoc?->longitude,
            'event_type' => 'DELIVERED',
            'event_time' => now(),
            'remarks' => 'Penerimaan pesanan kargo disetujui oleh Buyer. Barang telah tiba dan diterima dengan baik.',
            'created_by' => auth()->id() ?? $shipment->buyer_id,
        ]);

        AuditLog::create([
            'user_id' => auth()->id() ?? $shipment->buyer_id,
            'action' => 'BUYER_CONFIRM_RECEIPT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'DELIVERED'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        // Release escrow automatically if funds were held
        try {
            $this->escrowService->releaseToShipper($shipment, auth()->id());
        } catch (DomainException $e) {
            // If frozen by active exception, keep shipment DELIVERED note and inform caller cleanly
        }

        $this->notificationService->notify(
            $shipment->customer_id,
            $shipment->id,
            'DELIVERED',
            "Barang Diterima Buyer ({$shipment->tracking_number})",
            "Buyer telah mengonfirmasi penerimaan barang di {$destLoc?->name}. Siklus pengiriman selesai."
        );

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'items', 'escrow']),
            'Penerimaan pesanan berhasil disetujui oleh Buyer. Pengiriman selesai.'
        );
    }

    /**
     * POST /api/v1/shipments/{id}/release-document
     * Issue Digital Release / Pickup Document (Telex Release e-B/L, Air Waybill PDF, or Physical Courier record).
     */
    public function issueReleaseDocument(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Pengiriman tidak ditemukan', null, 404);
        }

        $user = $request->user();
        if ($user && $user->role === 'customer' && (int) $shipment->customer_id !== (int) $user->id) {
            return $this->sendError('Hanya Operator, Admin, atau Pengirim pemilik barang yang dapat menerbitkan Surat Rilis.', null, 403);
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'release_document_type' => ['required', 'in:TELEX_RELEASE,AIR_WAYBILL,PHYSICAL_COURIER'],
            'release_courier_tracking' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi dokumen rilis gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $prefix = $data['release_document_type'] === 'AIR_WAYBILL' ? 'AWB' : 'EBL';
        $docNum = $shipment->release_document_number ?: ($prefix . '-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5)));

        $shipment->update([
            'release_document_number' => $docNum,
            'release_document_type' => $data['release_document_type'],
            'release_courier_tracking' => $data['release_courier_tracking'] ?? null,
            'release_issued_at' => now(),
        ]);

        AuditLog::create([
            'user_id' => $user?->id,
            'action' => 'ISSUE_RELEASE_DOCUMENT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => null,
            'new_values' => [
                'release_document_number' => $docNum,
                'release_document_type' => $data['release_document_type'],
                'release_courier_tracking' => $data['release_courier_tracking'] ?? null,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        if ($shipment->buyer_id) {
            $this->notificationService->notify(
                $shipment->buyer_id,
                $shipment->id,
                'RELEASE_DOCUMENT',
                "Surat Pengambilan Barang Terbit ({$docNum})",
                "Surat rilis digital untuk pengiriman {$shipment->tracking_number} sudah siap dilihat dan dicetak."
            );
        }

        return $this->sendResponse(
            $shipment->fresh(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'currentLocation', 'items', 'escrow']),
            "Surat Rilis & Pengambilan Barang ({$docNum}) berhasil diterbitkan."
        );
    }

    protected function resolveWeatherData(Shipment $shipment): array
    {
        $weatherData = [];
        if ($shipment->currentLocation) {
            $weatherData['current'] = $this->weatherService->getWeatherForLocation($shipment->currentLocation);
        }
        if ($shipment->originLocation) {
            $weatherData['origin'] = $this->weatherService->getWeatherForLocation($shipment->originLocation);
        }
        if ($shipment->destinationLocation) {
            $weatherData['destination'] = $this->weatherService->getWeatherForLocation($shipment->destinationLocation);
        }

        return $weatherData;
    }
}
