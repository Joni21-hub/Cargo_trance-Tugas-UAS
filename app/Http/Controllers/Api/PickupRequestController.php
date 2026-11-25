<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\PickupRequest;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PickupRequestController extends BaseApiController
{
    public function __construct(
        protected NotificationService $notificationService
    ) {}

    /**
     * GET /api/v1/pickup-requests
     * Shipper sees own calls; Operator & Admin see incoming branch/all inspection calls.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $query = PickupRequest::with([
            'shipper:id,name,email,phone,company_name,address,city,country,is_verified',
            'buyer:id,name,email,phone,company_name,city,country,is_verified',
            'branchLocation:id,code,name,island,type,latitude,longitude',
            'destinationLocation:id,code,name,island,type,country,latitude,longitude',
            'assignedOperator:id,name,email,phone',
            'shipment:id,tracking_number,status',
        ]);

        if ($user->role === 'customer') {
            $query->where(function ($q) use ($user) {
                $q->where('shipper_id', $user->id)->orWhere('buyer_id', $user->id);
            });
        } elseif ($request->filled('branch_location_id')) {
            $query->where('branch_location_id', (int) $request->query('branch_location_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        $items = $query->latest()->limit(60)->get();

        return $this->sendResponse($items, 'Daftar panggilan penjemputan & cek fisik berhasil dimuat');
    }

    /**
     * POST /api/v1/pickup-requests
     * Shipper clicks "Buat Pengiriman" to call Branch Operator to physically inspect goods at Shipper's location.
     * Note: Shipper does NOT enter official weight/seal/container manifest — only Operator records official shipment!
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'buyer_id' => ['nullable', 'exists:users,id'],
            'branch_location_id' => ['required', 'exists:locations,id'],
            'destination_location_id' => ['nullable', 'exists:locations,id'],
            'pickup_city' => ['required', 'string', 'max:120'],
            'pickup_address' => ['required', 'string', 'max:500'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'target_days' => ['nullable', 'integer', 'min:1', 'max:120'],
            'preferred_mode' => ['nullable', 'in:SEA,AIR'],
            'agreed_escrow_usd' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'commodity_summary' => ['required', 'string', 'max:255'],
            'scheduled_note' => ['nullable', 'string', 'max:600'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi panggilan operator gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();

        // Smart routing rule: if target_days <= 7 days, auto-recommend AIR (Pesawat), else SEA (Kapal)
        $targetDays = isset($data['target_days']) ? (int) $data['target_days'] : null;
        $mode = $data['preferred_mode'] ?? (($targetDays !== null && $targetDays <= 7) ? 'AIR' : 'SEA');
        if ($targetDays !== null && $targetDays <= 7 && empty($data['preferred_mode'])) {
            $mode = 'AIR';
        }

        // Resolve well-known Indonesian city coordinates if not explicitly supplied
        [$lat, $lng] = $this->resolvePickupCoords(
            $data['pickup_city'],
            $data['pickup_latitude'] ?? null,
            $data['pickup_longitude'] ?? null
        );

        $pickup = PickupRequest::create([
            'request_code' => 'PKP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4)),
            'shipper_id' => $user->id,
            'buyer_id' => $data['buyer_id'] ?? null,
            'branch_location_id' => $data['branch_location_id'],
            'destination_location_id' => $data['destination_location_id'] ?? null,
            'pickup_city' => $data['pickup_city'],
            'pickup_address' => $data['pickup_address'],
            'pickup_latitude' => $lat,
            'pickup_longitude' => $lng,
            'target_days' => $targetDays,
            'preferred_mode' => $mode,
            'agreed_escrow_usd' => (float) ($data['agreed_escrow_usd'] ?? 0),
            'commodity_summary' => $data['commodity_summary'],
            'scheduled_note' => $data['scheduled_note'] ?? null,
            'status' => 'PENDING_INSPECTION',
        ]);

        // Notify operators at the chosen branch (or all operators if branch has none)
        $branchOperators = User::where('role', 'operator')
            ->where('location_id', $pickup->branch_location_id)
            ->get();

        if ($branchOperators->isEmpty()) {
            $branchOperators = User::where('role', 'operator')->get();
        }

        $modeLabel = $mode === 'AIR' ? 'Pesawat Kargo (Cepat)' : 'Kapal Laut (Reguler)';
        foreach ($branchOperators as $op) {
            $this->notificationService->notify(
                $op->id,
                null,
                'PICKUP_CALL',
                "Panggilan Cek Fisik Baru ({$pickup->request_code})",
                "{$user->name} meminta pengecekan & penjemputan di {$pickup->pickup_city} ({$pickup->commodity_summary}, Jalur {$modeLabel})."
            );
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'SHIPPER_CALL_OPERATOR',
            'model_type' => PickupRequest::class,
            'model_id' => $pickup->id,
            'old_values' => null,
            'new_values' => [
                'request_code' => $pickup->request_code,
                'pickup_city' => $pickup->pickup_city,
                'preferred_mode' => $pickup->preferred_mode,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $pickup->load(['shipper', 'buyer', 'branchLocation', 'destinationLocation']),
            'Panggilan berhasil dikirim ke Operator Cabang. Petugas akan datang dengan armada darat kami untuk mengecek fisik barang.',
            201
        );
    }

    /**
     * PATCH /api/v1/pickup-requests/{id}/dispatch
     * Operator dispatches internal land truck to Shipper's location for physical inspection.
     */
    public function dispatchTruck(Request $request, int $id): JsonResponse
    {
        $pickup = PickupRequest::find($id);
        if (!$pickup) {
            return $this->sendError('Permintaan penjemputan tidak ditemukan', null, 404);
        }

        $operator = $request->user();
        $pickup->update([
            'status' => 'OPERATOR_DISPATCHED',
            'assigned_operator_id' => $operator->id,
        ]);

        $this->notificationService->notify(
            $pickup->shipper_id,
            null,
            'OPERATOR_DISPATCHED',
            "Mobil Operator Menuju Lokasi Anda ({$pickup->request_code})",
            "Petugas {$operator->name} sedang menuju {$pickup->pickup_city} menggunakan armada truk cabang untuk memeriksa fisik barang."
        );

        AuditLog::create([
            'user_id' => $operator->id,
            'action' => 'OPERATOR_DISPATCH_TRUCK',
            'model_type' => PickupRequest::class,
            'model_id' => $pickup->id,
            'old_values' => ['status' => 'PENDING_INSPECTION'],
            'new_values' => ['status' => 'OPERATOR_DISPATCHED', 'assigned_operator_id' => $operator->id],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $pickup->fresh(['shipper', 'buyer', 'branchLocation', 'destinationLocation', 'assignedOperator']),
            'Armada truk cabang berhasil diberangkatkan menuju lokasi Pengirim.'
        );
    }

    /**
     * PATCH /api/v1/pickup-requests/{id}/cancel
     */
    public function cancel(Request $request, int $id): JsonResponse
    {
        $pickup = PickupRequest::find($id);
        if (!$pickup) {
            return $this->sendError('Permintaan penjemputan tidak ditemukan', null, 404);
        }

        $user = $request->user();
        if ($user->role === 'customer' && (int) $pickup->shipper_id !== (int) $user->id) {
            return $this->sendError('Tidak berhak membatalkan permintaan milik pihak lain.', null, 403);
        }

        $pickup->update(['status' => 'CANCELLED']);

        return $this->sendResponse($pickup, 'Panggilan penjemputan dibatalkan.');
    }

    protected function resolvePickupCoords(string $city, ?float $lat, ?float $lng): array
    {
        if ($lat !== null && $lng !== null) {
            return [$lat, $lng];
        }

        $c = strtolower($city);
        $presets = [
            'lhokseumawe' => [5.1801, 97.1507],
            'takengon' => [4.6295, 96.8458],
            'gayo' => [4.6295, 96.8458],
            'aceh' => [5.5483, 95.3238],
            'curup' => [-3.4658, 102.5210],
            'bengkulu' => [-3.7928, 102.2608],
            'medan' => [3.5952, 98.6722],
            'lintong' => [2.2562, 98.8590],
            'bandung' => [-6.9175, 107.6191],
            'jakarta' => [-6.2088, 106.8456],
            'surabaya' => [-7.2575, 112.7521],
            'kayong' => [-1.2535, 109.9541],
            'pontianak' => [-0.0263, 109.3425],
            'kapuas' => [0.8594, 112.9275],
            'toraja' => [-3.0815, 119.8606],
            'enrekang' => [-3.5620, 119.7760],
            'mamasa' => [-2.9367, 119.3831],
            'makassar' => [-5.1477, 119.4327],
            'wamena' => [-4.0982, 138.9478],
            'jayapura' => [-2.5916, 140.6690],
        ];

        foreach ($presets as $key => $coords) {
            if (str_contains($c, $key)) {
                return $coords;
            }
        }

        return [null, null];
    }
}
