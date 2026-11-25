<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseApiController
{
    /**
     * POST /api/v1/login
     * Authenticate user with email & password using Laravel session.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $credentials = $validator->validated();
        $normalizedEmail = strtolower(trim($credentials['email']));

        // Map convenient login aliases to primary seeded account emails (supports @gmail.com and @cargotrace.com)
        $aliasMap = [
            'shipper@gmail.com' => 'shipper@cargotrace.com',
            'customer@gmail.com' => 'shipper@cargotrace.com',
            'customer@cargotrace.com' => 'shipper@cargotrace.com',
            'buyer@gmail.com' => 'buyer@cargotrace.com',
            'operator@gmail.com' => 'operator@cargotrace.com',
            'admin@gmail.com' => 'admin@cargotrace.com',
        ];

        if (isset($aliasMap[$normalizedEmail])) {
            $credentials['email'] = $aliasMap[$normalizedEmail];
        }

        if (!Auth::attempt($credentials, true)) {
            return $this->sendError('Invalid email or password.', null, 401);
        }

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        /** @var User $user */
        $user = Auth::user();

        return $this->sendResponse([
            'user' => $this->formatUser($user),
        ], 'Authenticated successfully');
    }

    /**
     * POST /api/v1/register
     * Public registration is strictly locked to 'customer' role; Operators & Admins are provisioned only by Admin.
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:500'],
            'identity_number' => ['nullable', 'string', 'max:80'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'company_name' => $data['company_name'] ?? null,
            'city' => $data['city'] ?? null,
            'country' => $data['country'] ?? 'Indonesia',
            'address' => $data['address'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'wallet_balance_usd' => 0,
            'is_verified' => false,
        ]);

        Auth::login($user, true);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return $this->sendResponse([
            'user' => $this->formatUser($user),
        ], 'Account registered successfully', 201);
    }

    /**
     * POST /api/v1/logout
     */
    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->sendResponse(null, 'Signed out successfully');
    }

    /**
     * GET /api/v1/user
     */
    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return $this->sendError('Unauthenticated', null, 401);
        }

        return $this->sendResponse($this->formatUser($user), 'User profile retrieved');
    }

    protected function formatUser(User $user): array
    {
        $user->loadMissing('branchLocation:id,code,name,island,type');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'company_name' => $user->company_name,
            'address' => $user->address,
            'city' => $user->city,
            'country' => $user->country,
            'identity_number' => $user->identity_number,
            'bio' => $user->bio,
            'is_verified' => (bool) $user->is_verified,
            'verified_at' => $user->verified_at,
            'location_id' => $user->location_id,
            'branch_location' => $user->branchLocation,
            'wallet_balance_usd' => (float) ($user->wallet_balance_usd ?? 0),
        ];
    }
}
