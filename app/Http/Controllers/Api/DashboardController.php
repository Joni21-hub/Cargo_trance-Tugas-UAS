<?php

namespace App\Http\Controllers\Api;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    /**
     * GET /api/v1/bootstrap
     * Return master data, authenticated user, unread notifications count, and live database KPI metrics.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('branchLocation:id,code,name');
        }

        $locations = Location::orderBy('name')->get();
        $carriers = Carrier::where('is_active', true)->orderBy('name')->get();
        $customers = User::where('role', 'customer')
            ->select('id', 'name', 'email', 'role')
            ->orderBy('id')
            ->get();

        // Scope KPIs if authenticated as customer
        $shipQuery = Shipment::query();
        if ($user && $user->role === 'customer') {
            $shipQuery->where(function ($q) use ($user) {
                $q->where('customer_id', $user->id)->orWhere('buyer_id', $user->id);
            });
        }

        $stats = [
            'total_shipments' => (clone $shipQuery)->count(),
            'pending_confirmation' => (clone $shipQuery)->where('status', 'CREATED')->count(),
            'in_transit' => (clone $shipQuery)->whereIn('status', ['CONFIRMED', 'BOOKED', 'PICKED_UP', 'IN_TRANSIT'])->count(),
            'delivered' => (clone $shipQuery)->where('status', 'DELIVERED')->count(),
            'delayed' => (clone $shipQuery)->where('is_delayed', true)->count(),
            'open_exceptions' => ShipmentException::whereIn('status', ['OPEN', 'INVESTIGATING', 'UNDER_INVESTIGATION'])->count(),
        ];

        $unreadNotifications = $user
            ? AppNotification::where('user_id', $user->id)->where('is_read', false)->count()
            : 0;

        return $this->sendResponse([
            'user' => $user ? [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'location_id' => $user->location_id,
                'branch_location' => $user->branchLocation,
                'wallet_balance_usd' => (float) ($user->wallet_balance_usd ?? 0),
            ] : null,
            'unread_notifications' => $unreadNotifications,
            'locations' => $locations,
            'carriers' => $carriers,
            'customers' => $customers,
            'stats' => $stats,
            'delay_threshold_hours' => (int) config('cargotrace.delay_threshold_hours', 12),
            'demo_tools_enabled' => !app()->isProduction() || (bool) config('cargotrace.enable_demo_tools', false),
        ], 'Platform metadata retrieved successfully');
    }

    /**
     * GET /api/v1/audit-logs
     */
    public function auditLogs(): JsonResponse
    {
        $logs = AuditLog::with('user:id,name,email,role')
            ->latest()
            ->limit(60)
            ->get();

        return $this->sendResponse($logs, 'Audit logs retrieved successfully');
    }
}
