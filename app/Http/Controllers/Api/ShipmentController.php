<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreShipmentRequest;
use App\Http\Requests\UpdateShipmentRequest;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentItem;
use App\Models\TrackingEvent;
use App\Services\EtaCalculationService;
use App\Services\ShipmentLifecycleService;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShipmentController extends BaseApiController
{
    public function __construct(
        protected EtaCalculationService $etaService,
        protected WeatherService $weatherService,
        protected ShipmentLifecycleService $lifecycleService
    ) {}

    /**
     * GET /api/v1/shipments
     * List shipments with search and filters.
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
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->query('customer_id'));
        }

        if ($request->filled('buyer_id')) {
            $query->where('buyer_id', $request->query('buyer_id'));
        }

        if ($request->filled('carrier_id')) {
            $query->where('carrier_id', $request->query('carrier_id'));
        }

        if ($request->filled('container_number')) {
            $query->where('container_number', $request->query('container_number'));
        }

        if ($request->has('is_delayed')) {
            $query->where('is_delayed', filter_var($request->query('is_delayed'), FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->where(function ($q) use ($search) {
                $q->where('tracking_number', 'like', "%{$search}%")
                  ->orWhere('container_number', 'like', "%{$search}%")
                  ->orWhere('cargo_description', 'like', "%{$search}%")
                  ->orWhereHas('carrier', fn($c) => $c->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('originLocation', fn($l) => $l->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('destinationLocation', fn($l) => $l->where('name', 'like', "%{$search}%"));
            });
        }

        $perPage = (int) $request->query('per_page', 15);
        $shipments = $query->latest()->paginate($perPage);

        return $this->sendResponse($shipments, 'Shipments retrieved successfully');
    }

    /**
     * GET /api/v1/shipments/{tracking_number}
     * Retrieve single shipment by tracking number, container number, or ID with route and live weather.
     */
    public function show(string $trackingNumber): JsonResponse
    {
        $shipment = Shipment::with([
            'carrier',
            'customer',
            'buyer',
            'originLocation',
            'destinationLocation',
            'currentLocation',
            'items',
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

        // Attach live port weather for current / origin / destination locations
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

        $result = $shipment->toArray();
        $result['live_weather'] = $weatherData;

        return $this->sendResponse($result, 'Shipment details retrieved successfully');
    }

    /**
     * POST /api/v1/shipments
     * Operator registers a new cargo shipment with detail barang.
     */
    public function store(StoreShipmentRequest $request): JsonResponse
    {
        $data = $request->validated();

        if (empty($data['tracking_number'])) {
            $data['tracking_number'] = 'CTG-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        }

        $data['current_location_id'] = $data['origin_location_id'];
        $data['calculated_eta'] = $data['expected_arrival'];
        $data['status'] = $data['status'] ?? 'CREATED';
        $data['is_delayed'] = false;
        $data['delay_hours'] = 0;

        $itemsData = $data['items'] ?? null;
        unset($data['items']);

        $shipment = Shipment::create($data);

        // Simpan detail barang jika dimasukkan oleh Operator
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
            // Default item dari deskripsi utama jika tidak ada array items
            $shipment->items()->create([
                'item_name' => $shipment->cargo_description,
                'quantity' => 1,
                'weight_kg' => $shipment->weight_kg,
                'condition' => 'BAIK & TERSEGEL',
            ]);
        }

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'items']),
            'Shipment created successfully by Operator',
            201
        );
    }

    /**
     * PUT/PATCH /api/v1/shipments/{id}
     * Update an existing shipment.
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
        $shipment->update($data);

        return $this->sendResponse(
            $shipment->fresh(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'currentLocation', 'items']),
            'Shipment updated successfully'
        );
    }

    /**
     * DELETE /api/v1/shipments/{id}
     * Delete an existing shipment.
     */
    public function destroy(string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $shipment->delete();

        return $this->sendResponse(null, 'Shipment deleted successfully');
    }

    /**
     * GET /api/v1/shipments/{id}/tracking
     * Retrieve tracking events for a shipment.
     */
    public function tracking(string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $events = $shipment->trackingEvents()->with(['location', 'creator'])->get();

        return $this->sendResponse($events, 'Tracking history retrieved successfully');
    }

    /**
     * POST /api/v1/shipments/{id}/confirm-shipper
     * Shipper mengonfirmasi & menyetujui detail barang yang diisi oleh Operator.
     */
    public function confirmByShipper(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        // Transisi status: CREATED -> CONFIRMED (atau IN_TRANSIT)
        if ($shipment->status === 'CREATED' || $shipment->status === 'BOOKED') {
            $this->lifecycleService->transition($shipment, 'CONFIRMED');
        }

        // Catat Tracking Event
        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $shipment->origin_location_id,
            'event_type' => 'CONFIRMED_BY_SHIPPER',
            'event_time' => now(),
            'remarks' => 'Barang & dokumen ekspor disetujui serta dikonfirmasi oleh Shipper.',
            'created_by' => auth()->id() ?? $shipment->customer_id,
        ]);

        // Catat Audit Log
        AuditLog::create([
            'user_id' => auth()->id() ?? $shipment->customer_id,
            'action' => 'SHIPPER_CONFIRM_CARGO',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['status' => 'CREATED'],
            'new_values' => ['status' => $shipment->status],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'items']),
            'Kargo berhasil dikonfirmasi dan disetujui oleh Shipper.'
        );
    }

    /**
     * POST /api/v1/shipments/{id}/confirm-buyer
     * Buyer menyetujui penerimaan pesanan kargo.
     */
    public function confirmReceiptByBuyer(Request $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $oldStatus = $shipment->status;

        // Transisi status ke DELIVERED
        $this->lifecycleService->transition($shipment, 'DELIVERED');

        // Update current location ke destination
        $shipment->current_location_id = $shipment->destination_location_id;
        $shipment->save();

        // Catat Tracking Event
        TrackingEvent::create([
            'shipment_id' => $shipment->id,
            'location_id' => $shipment->destination_location_id,
            'event_type' => 'DELIVERED',
            'event_time' => now(),
            'remarks' => 'Penerimaan pesanan kargo disetujui oleh Buyer. Barang telah tiba dan diterima dengan baik.',
            'created_by' => auth()->id() ?? $shipment->buyer_id,
        ]);

        // Catat Audit Log
        AuditLog::create([
            'user_id' => auth()->id() ?? $shipment->buyer_id,
            'action' => 'BUYER_CONFIRM_RECEIPT',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => 'DELIVERED'],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'items']),
            'Penerimaan pesanan berhasil disetujui oleh Buyer. Pengiriman selesai.'
        );
    }
}
