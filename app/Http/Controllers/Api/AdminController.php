<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminController extends BaseApiController
{
    /**
     * GET /api/v1/admin/overview
     * Executive analytics and master counts computed live from database.
     */
    public function overview(): JsonResponse
    {
        $totalShipments = Shipment::count();
        $delivered = Shipment::where('status', 'DELIVERED')->count();
        $delayed = Shipment::where('is_delayed', true)->count();
        $inTransit = Shipment::whereIn('status', ['CONFIRMED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT'])->count();
        $pending = Shipment::where('status', 'CREATED')->count();
        $openExceptions = ShipmentException::whereIn('status', ['OPEN', 'INVESTIGATING', 'UNDER_INVESTIGATION'])->count();

        $onTimeRate = $totalShipments > 0
            ? round((($totalShipments - $delayed) / $totalShipments) * 100, 1)
            : 100.0;

        $byStatus = Shipment::selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $byCarrier = Carrier::withCount('shipments')
            ->orderByDesc('shipments_count')
            ->get(['id', 'code', 'name', 'transport_mode', 'is_active']);

        return $this->sendResponse([
            'metrics' => [
                'total_shipments' => $totalShipments,
                'in_transit' => $inTransit,
                'pending' => $pending,
                'delivered' => $delivered,
                'delayed' => $delayed,
                'open_exceptions' => $openExceptions,
                'on_time_rate' => $onTimeRate,
                'total_users' => User::count(),
                'total_carriers' => Carrier::count(),
                'total_locations' => Location::count(),
            ],
            'by_status' => $byStatus,
            'by_carrier' => $byCarrier,
            'delay_threshold_hours' => (int) config('cargotrace.delay_threshold_hours', 12),
        ], 'Admin executive overview retrieved');
    }

    /**
     * GET /api/v1/admin/users
     */
    public function users(): JsonResponse
    {
        $users = User::with('branchLocation:id,code,name')
            ->select('id', 'name', 'email', 'role', 'location_id', 'wallet_balance_usd', 'created_at')
            ->orderBy('id')
            ->get();

        return $this->sendResponse($users, 'Users retrieved successfully');
    }

    /**
     * POST /api/v1/admin/users
     * Exclusively allows Admin to provision Operator, Admin, Surveyor, or Customer accounts.
     */
    public function storeUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'in:admin,operator,customer,surveyor'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'wallet_balance_usd' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
            'location_id' => $data['location_id'] ?? null,
            'wallet_balance_usd' => $data['wallet_balance_usd'] ?? 0,
        ]);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_CREATE_USER',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => null,
            'new_values' => [
                'email' => $user->email,
                'role' => $user->role,
                'location_id' => $user->location_id,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $user->load('branchLocation:id,code,name')->only('id', 'name', 'email', 'role', 'location_id', 'branchLocation', 'wallet_balance_usd', 'created_at'),
            'Pengguna baru berhasil ditambahkan oleh Admin',
            201
        );
    }

    /**
     * PUT/PATCH /api/v1/admin/users/{id}
     * Admin full edit control over user profile, role, branch location, and password.
     */
    public function updateUser(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'email', 'max:150', 'unique:users,email,' . $user->id],
            'role' => ['sometimes', 'required', 'in:admin,operator,customer,surveyor'],
            'location_id' => ['nullable', 'exists:locations,id'],
            'password' => ['nullable', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $oldValues = $user->only('name', 'email', 'role', 'location_id');

        if (array_key_exists('name', $data)) {
            $user->name = $data['name'];
        }
        if (array_key_exists('email', $data)) {
            $user->email = $data['email'];
        }
        if (array_key_exists('role', $data)) {
            $user->role = $data['role'];
        }
        if (array_key_exists('location_id', $data)) {
            $user->location_id = $data['location_id'] ?: null;
        }
        if (!empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }
        $user->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_UPDATE_USER',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $user->only('name', 'email', 'role', 'location_id'),
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse(
            $user->load('branchLocation:id,code,name')->only('id', 'name', 'email', 'role', 'location_id', 'branchLocation', 'wallet_balance_usd'),
            'Data pengguna berhasil diperbarui oleh Admin'
        );
    }

    /**
     * PATCH /api/v1/admin/users/{id}/role
     */
    public function updateUserRole(Request $request, int $id): JsonResponse
    {
        $user = User::find($id);
        if (!$user) {
            return $this->sendError('Pengguna tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'role' => ['required', 'in:admin,operator,customer,surveyor'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $oldRole = $user->role;
        $user->role = $validator->validated()['role'];
        $user->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_UPDATE_USER_ROLE',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => ['role' => $oldRole],
            'new_values' => ['role' => $user->role],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($user->only('id', 'name', 'email', 'role', 'location_id'), 'Peran pengguna diperbarui');
    }

    /**
     * GET /api/v1/admin/carriers
     */
    public function carriers(): JsonResponse
    {
        $carriers = Carrier::withCount('shipments')->orderBy('name')->get();
        return $this->sendResponse($carriers, 'Carriers retrieved');
    }

    /**
     * POST /api/v1/admin/carriers
     */
    public function storeCarrier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:10', 'unique:carriers,code'],
            'name' => ['required', 'string', 'max:150'],
            'transport_mode' => ['required', 'in:SEA,AIR,LAND'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper(trim($data['code']));
        $data['is_active'] = true;

        $carrier = Carrier::create($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_CREATE_CARRIER',
            'model_type' => Carrier::class,
            'model_id' => $carrier->id,
            'old_values' => null,
            'new_values' => ['code' => $carrier->code, 'name' => $carrier->name],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($carrier, 'Maskapai pelayaran berhasil ditambahkan', 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/carriers/{id}
     */
    public function updateCarrier(Request $request, int $id): JsonResponse
    {
        $carrier = Carrier::find($id);
        if (!$carrier) {
            return $this->sendError('Maskapai tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'max:10', 'unique:carriers,code,' . $carrier->id],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'transport_mode' => ['sometimes', 'required', 'in:SEA,AIR,LAND'],
            'contact_email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $oldValues = $carrier->only('code', 'name', 'transport_mode', 'contact_email');
        $carrier->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_UPDATE_CARRIER',
            'model_type' => Carrier::class,
            'model_id' => $carrier->id,
            'old_values' => $oldValues,
            'new_values' => $carrier->only('code', 'name', 'transport_mode', 'contact_email'),
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($carrier, 'Data maskapai diperbarui');
    }

    /**
     * PATCH /api/v1/admin/carriers/{id}/toggle
     */
    public function toggleCarrier(Request $request, int $id): JsonResponse
    {
        $carrier = Carrier::find($id);
        if (!$carrier) {
            return $this->sendError('Maskapai tidak ditemukan', null, 404);
        }

        $carrier->is_active = !$carrier->is_active;
        $carrier->save();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_TOGGLE_CARRIER',
            'model_type' => Carrier::class,
            'model_id' => $carrier->id,
            'old_values' => ['is_active' => !$carrier->is_active],
            'new_values' => ['is_active' => $carrier->is_active],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($carrier, 'Status maskapai diperbarui');
    }

    /**
     * POST /api/v1/admin/locations
     */
    public function storeLocation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => ['required', 'string', 'max:10', 'unique:locations,code'],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', 'in:PORT,WAREHOUSE,AIRPORT,HUB'],
            'country' => ['required', 'string', 'max:100'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $data['code'] = strtoupper(trim($data['code']));

        $location = Location::create($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_CREATE_LOCATION',
            'model_type' => Location::class,
            'model_id' => $location->id,
            'old_values' => null,
            'new_values' => ['code' => $location->code, 'name' => $location->name],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($location, 'Pelabuhan / lokasi baru berhasil ditambahkan', 201);
    }

    /**
     * PUT/PATCH /api/v1/admin/locations/{id}
     */
    public function updateLocation(Request $request, int $id): JsonResponse
    {
        $location = Location::find($id);
        if (!$location) {
            return $this->sendError('Lokasi tidak ditemukan', null, 404);
        }

        $validator = Validator::make($request->all(), [
            'code' => ['sometimes', 'required', 'string', 'max:10', 'unique:locations,code,' . $location->id],
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'type' => ['sometimes', 'required', 'in:PORT,WAREHOUSE,AIRPORT,HUB'],
            'country' => ['sometimes', 'required', 'string', 'max:100'],
            'latitude' => ['sometimes', 'required', 'numeric', 'between:-90,90'],
            'longitude' => ['sometimes', 'required', 'numeric', 'between:-180,180'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        if (isset($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }
        $oldValues = $location->only('code', 'name', 'type', 'country', 'latitude', 'longitude');
        $location->update($data);

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => 'ADMIN_UPDATE_LOCATION',
            'model_type' => Location::class,
            'model_id' => $location->id,
            'old_values' => $oldValues,
            'new_values' => $location->only('code', 'name', 'type', 'country', 'latitude', 'longitude'),
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return $this->sendResponse($location, 'Data pelabuhan / lokasi berhasil diperbarui');
    }
}
