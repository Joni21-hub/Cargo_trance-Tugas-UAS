<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Support\Facades\DB;

class ExceptionManagementService
{
    public function __construct(
        protected EtaCalculationService $etaService
    ) {}

    /**
     * Record a new shipment exception and trigger ETA recalculation.
     */
    public function recordException(Shipment $shipment, array $data, ?int $userId = null): ShipmentException
    {
        return DB::transaction(function () use ($shipment, $data, $userId) {
            $exception = ShipmentException::create([
                'shipment_id' => $shipment->id,
                'tracking_event_id' => $data['tracking_event_id'] ?? null,
                'exception_type' => $data['exception_type'],
                'severity' => $data['severity'] ?? 'MEDIUM',
                'impact_hours' => $data['impact_hours'] ?? 0,
                'description' => $data['description'],
                'status' => 'OPEN',
            ]);

            // Recalculate ETA and is_delayed flag
            $this->etaService->recalculate($shipment);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CREATE_SHIPMENT_EXCEPTION',
                'model_type' => ShipmentException::class,
                'model_id' => $exception->id,
                'old_values' => null,
                'new_values' => $exception->toArray(),
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $exception;
        });
    }

    /**
     * Resolve an exception and trigger ETA recalculation.
     */
    public function resolveException(ShipmentException $exception, string $resolutionNotes, ?int $userId = null): ShipmentException
    {
        return DB::transaction(function () use ($exception, $resolutionNotes, $userId) {
            $oldStatus = $exception->status;

            $exception->update([
                'status' => 'RESOLVED',
                'resolved_at' => now(),
                'resolution_notes' => $resolutionNotes,
            ]);

            $shipment = $exception->shipment;
            $this->etaService->recalculate($shipment);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'RESOLVE_SHIPMENT_EXCEPTION',
                'model_type' => ShipmentException::class,
                'model_id' => $exception->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => [
                    'status' => 'RESOLVED',
                    'resolution_notes' => $resolutionNotes,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $exception;
        });
    }
}
