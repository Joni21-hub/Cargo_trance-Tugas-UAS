<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Services\EtaCalculationService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DemoTimeTravelController extends BaseApiController
{
    public function __construct(
        protected EtaCalculationService $etaService,
        protected NotificationService $notificationService
    ) {}

    /**
     * POST /api/v1/demo/time-travel
     * Simulate elapsed sailing hours for live UAS demo & deterministic delay testing.
     */
    public function advance(Request $request): JsonResponse
    {
        if (app()->isProduction() && !config('cargotrace.enable_demo_tools', false)) {
            return $this->sendError('Fitur simulasi waktu dinonaktifkan di lingkungan produksi.', null, 403);
        }

        $validator = Validator::make($request->all(), [
            'shipment_id' => ['required', 'exists:shipments,id'],
            'hours' => ['required', 'integer', 'min:1', 'max:720'],
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validasi gagal', $validator->errors(), 422);
        }

        $data = $validator->validated();
        $hours = (int) $data['hours'];

        /** @var Shipment $shipment */
        $shipment = Shipment::findOrFail($data['shipment_id']);

        if (in_array($shipment->status, ['DELIVERED', 'CANCELLED'], true)) {
            return $this->sendError('Simulasi waktu tidak berlaku untuk pengiriman yang sudah selesai/dibatalkan.', null, 422);
        }

        // Shift expected_departure & expected_arrival backward to simulate time passing cleanly
        $oldExpectedArrival = $shipment->expected_arrival?->toDateTimeString();
        $shipment->expected_departure = Carbon::parse($shipment->expected_departure)->subHours($hours);
        $shipment->expected_arrival = Carbon::parse($shipment->expected_arrival)->subHours($hours);
        $shipment->simulated_days_elapsed = (int) ($shipment->simulated_days_elapsed ?? 0) + max(1, (int) round($hours / 24));
        if (in_array($shipment->status, ['CONFIRMED', 'BOOKED', 'PICKED_UP'], true)) {
            $shipment->status = 'IN_TRANSIT';
        }
        $shipment->save();

        // Check if shipment has now passed expected_arrival while still not DELIVERED
        $thresholdHours = (int) config('cargotrace.delay_threshold_hours', 12);
        $overdueHours = max(0, (int) Carbon::parse($shipment->expected_arrival)->diffInHours(now(), false));

        if ($overdueHours > 0) {
            $shipment->calculated_eta = now()->addHours(max(1, (int) $shipment->activeExceptions()->sum('impact_hours')));
            $shipment->delay_hours = max((int) $shipment->delay_hours, $overdueHours);
            $shipment->is_delayed = $shipment->delay_hours >= $thresholdHours;
            $shipment->save();
        } else {
            $this->etaService->recalculate($shipment);
        }

        $shipment->refresh();

        AuditLog::create([
            'user_id' => $request->user()?->id,
            'action' => 'DEMO_TIME_TRAVEL',
            'model_type' => Shipment::class,
            'model_id' => $shipment->id,
            'old_values' => ['expected_arrival' => $oldExpectedArrival],
            'new_values' => [
                'simulated_hours_advanced' => $hours,
                'expected_arrival' => $shipment->expected_arrival?->toDateTimeString(),
                'is_delayed' => $shipment->is_delayed,
                'delay_hours' => $shipment->delay_hours,
            ],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        if ($shipment->is_delayed) {
            $this->notificationService->notifyParties(
                $shipment,
                'DELAY_WARNING',
                "Peringatan Terlambat ({$shipment->tracking_number})",
                "Waktu jadwal tiba telah terlewati (+{$shipment->delay_hours} jam, keparahan {$shipment->delay_severity})."
            );
        }

        return $this->sendResponse([
            'shipment' => $shipment->load(['carrier', 'customer', 'buyer', 'originLocation', 'destinationLocation', 'currentLocation', 'escrow']),
            'hours_advanced' => $hours,
        ], "Simulasi waktu maju +{$hours} jam berhasil dijalankan.");
    }
}
