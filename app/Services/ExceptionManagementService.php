<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\ShipmentException;
use Illuminate\Support\Facades\DB;

class ExceptionManagementService
{
    public function __construct(
        protected EtaCalculationService $etaService,
        protected EscrowService $escrowService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Record a new shipment exception, freeze escrow if severe, notify parties, and trigger ETA recalculation.
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
                'detected_at' => now(),
            ]);

            // Recalculate ETA and is_delayed flag
            $this->etaService->recalculate($shipment);

            // Protect escrow payout if applicable
            $this->escrowService->freezeOnException($shipment, $exception);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CREATE_SHIPMENT_EXCEPTION',
                'model_type' => ShipmentException::class,
                'model_id' => $exception->id,
                'old_values' => null,
                'new_values' => $exception->toArray(),
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $this->notificationService->notifyParties(
                $shipment,
                'EXCEPTION_ALERT',
                "Gangguan {$exception->exception_type} ({$shipment->tracking_number})",
                "Terjadi kendala ({$exception->severity}, dampak +{$exception->impact_hours} jam): {$exception->description}"
            );

            return $exception;
        });
    }

    /**
     * Move an open exception into INVESTIGATING status.
     */
    public function investigateException(ShipmentException $exception, ?string $notes = null, ?int $userId = null): ShipmentException
    {
        return DB::transaction(function () use ($exception, $notes, $userId) {
            $oldStatus = $exception->status;

            $exception->update([
                'status' => 'INVESTIGATING',
                'resolution_notes' => $notes ?? $exception->resolution_notes,
            ]);

            AuditLog::create([
                'user_id' => $userId,
                'action' => 'INVESTIGATE_SHIPMENT_EXCEPTION',
                'model_type' => ShipmentException::class,
                'model_id' => $exception->id,
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => 'INVESTIGATING', 'notes' => $notes],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $exception->fresh();
        });
    }

    /**
     * Resolve an exception, unfreeze escrow if clear, and trigger ETA recalculation.
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
            $this->escrowService->unfreezeIfClear($shipment);

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

            $this->notificationService->notifyParties(
                $shipment,
                'EXCEPTION_ALERT',
                "Gangguan Selesai ({$shipment->tracking_number})",
                "Kendala {$exception->exception_type} telah diselesaikan: {$resolutionNotes}"
            );

            return $exception;
        });
    }
}
