<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends BaseApiController
{
    /**
     * GET /api/v1/profile
     * Return authenticated user's complete identity profile.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->loadMissing('branchLocation:id,code,name,island,type');

        return $this->sendResponse($this->formatProfile($user), 'Profil pengguna berhasil dimuat');
    }

    /**
     * PUT/PATCH /api/v1/profile
     * Update authenticated user's identity profile (phone, company, address, NIB/NPWP, bio).
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'identity_number' => ['nullable', 'string', 'max:80'],
            'bio' => ['nullable', 'string', 'max:600'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi profil gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $oldValues = $user->only(array_keys($data));
        $user->update($data);

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'UPDATE_PROFILE',
            'model_type' => User::class,
            'model_id' => $user->id,
            'old_values' => $oldValues,
            'new_values' => $user->only(array_keys($data)),
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $user->loadMissing('branchLocation:id,code,name,island,type');

        return $this->sendResponse($this->formatProfile($user), 'Profil dan identitas berhasil diperbarui');
    }

    /**
     * GET /api/v1/users/{id}/public-profile
     * Transparent identity card visible to Admin, Buyer, Shipper, and Operator.
     */
    public function publicProfile(int $id): JsonResponse
    {
        $user = User::with('branchLocation:id,code,name,island,type')
            ->find($id);

        if (!$user) {
            return $this->sendError('Profil pengguna tidak ditemukan', null, 404);
        }

        return $this->sendResponse($this->formatProfile($user), 'Profil identitas berhasil dimuat');
    }

    /**
     * GET /api/v1/operators
     * Public directory of regional branch operators across the 5 major Indonesian islands.
     */
    public function operators(): JsonResponse
    {
        $operators = User::with('branchLocation:id,code,name,island,type,country')
            ->where('role', 'operator')
            ->orderBy('id')
            ->get()
            ->map(fn (User $u) => $this->formatProfile($u));

        return $this->sendResponse($operators, 'Daftar petugas cabang operator berhasil dimuat');
    }

    protected function formatProfile(User $u): array
    {
        return [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'role' => $u->role,
            'phone' => $u->phone,
            'company_name' => $u->company_name,
            'address' => $u->address,
            'city' => $u->city,
            'country' => $u->country,
            'identity_number' => $u->identity_number,
            'bio' => $u->bio,
            'is_verified' => (bool) $u->is_verified,
            'verified_at' => $u->verified_at,
            'location_id' => $u->location_id,
            'branch_location' => $u->branchLocation,
            'wallet_balance_usd' => (float) ($u->wallet_balance_usd ?? 0),
        ];
    }
}
