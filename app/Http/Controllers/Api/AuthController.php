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
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation failed', $validator->errors(), 422);
        }

        $data = $validator->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => 'customer',
            'wallet_balance_usd' => 0,
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
        $user->loadMissing('branchLocation:id,code,name');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'location_id' => $user->location_id,
            'branch_location' => $user->branchLocation,
            'wallet_balance_usd' => (float) ($user->wallet_balance_usd ?? 0),
        ];
    }
}
