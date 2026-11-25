<?php

namespace App\Http\Controllers\Api;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Carrier;
use App\Models\ChatMessage;
use App\Models\ExportProduct;
use App\Models\Location;
use App\Models\PickupRequest;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends BaseApiController
{
    /**
     * GET /api/v1/bootstrap
     * Return master data, authenticated user, unread notifications/chats count, and live database KPI metrics.
     */
    public function bootstrap(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $user->loadMissing('branchLocation:id,code,name,island,type');
        }

        $locations = Location::orderByRaw("CASE WHEN island = 'Internasional' THEN 1 ELSE 0 END")
            ->orderBy('island')
            ->orderBy('type', 'desc')
            ->orderBy('name')
            ->get();
        $carriers = Carrier::where('is_active', true)->orderBy('transport_mode')->orderBy('name')->get();
        $customers = User::where('role', 'customer')
            ->select('id', 'name', 'email', 'role', 'phone', 'company_name', 'address', 'city', 'country', 'identity_number', 'is_verified')
            ->orderBy('id')
            ->get();
        $operators = User::where('role', 'operator')
            ->with('branchLocation:id,code,name,island,type')
            ->select('id', 'name', 'email', 'role', 'phone', 'company_name', 'address', 'city', 'location_id', 'is_verified')
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
            'pending_pickups' => PickupRequest::whereIn('status', ['PENDING_INSPECTION', 'OPERATOR_DISPATCHED'])->count(),
            'open_tickets' => SupportTicket::whereIn('status', ['OPEN', 'IN_PROGRESS'])->count(),
            'coffee_products' => ExportProduct::where('is_active', true)->count(),
        ];

        $unreadNotifications = $user
            ? AppNotification::where('user_id', $user->id)->where('is_read', false)->count()
            : 0;
        $unreadChats = $user
            ? ChatMessage::where('recipient_id', $user->id)->where('is_read', false)->count()
            : 0;

        return $this->sendResponse([
            'user' => $user ? [
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
            ] : null,
            'unread_notifications' => $unreadNotifications,
            'unread_chats' => $unreadChats,
            'locations' => $locations,
            'carriers' => $carriers,
            'customers' => $customers,
            'operators' => $operators,
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
