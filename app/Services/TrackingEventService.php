<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\Shipment;
use App\Models\TrackingEvent;
use Illuminate\Support\Facades\DB;

class TrackingEventService
{
    public function __construct(
        protected ShipmentLifecycleService $lifecycleService,
        protected NotificationService $notificationService
    ) {}

    /**
     * Record a new tracking event, sync shipment location & status, notify parties, and record audit log.
     */
    public function recordEvent(Shipment $shipment, array $data, ?int $userId = null): TrackingEvent
    {
        return DB::transaction(function () use ($shipment, $data, $userId) {
            $location = Location::find($data['location_id']);

            $event = TrackingEvent::create([
                'shipment_id' => $shipment->id,
                'location_id' => $data['location_id'],
                'latitude' => $data['latitude'] ?? $location?->latitude,
                'longitude' => $data['longitude'] ?? $location?->longitude,
                'event_type' => $data['event_type'],
                'event_time' => $data['event_time'] ?? now(),
                'remarks' => $data['remarks'] ?? null,
                'created_by' => $userId,
            ]);

            $oldStatus = $shipment->status;
            $oldLocationId = $shipment->current_location_id;

            // Update current location of shipment
            $shipment->current_location_id = $data['location_id'];

            // Automatic status synchronization
            if ($data['event_type'] === 'DEPARTURE' && in_array($shipment->status, ['PICKED_UP', 'CONFIRMED', 'BOOKED'])) {
                $this->lifecycleService->transition($shipment, 'IN_TRANSIT');
            } elseif ($data['event_type'] === 'DELIVERED' && in_array($shipment->status, ['IN_TRANSIT', 'PICKED_UP', 'CONFIRMED'])) {
                $this->lifecycleService->transition($shipment, 'DELIVERED');
            } else {
                $shipment->save();
            }

            // Create audit log
            AuditLog::create([
                'user_id' => $userId,
                'action' => 'CREATE_TRACKING_EVENT',
                'model_type' => Shipment::class,
                'model_id' => $shipment->id,
                'old_values' => [
                    'status' => $oldStatus,
                    'current_location_id' => $oldLocationId,
                ],
                'new_values' => [
                    'status' => $shipment->status,
                    'current_location_id' => $shipment->current_location_id,
                    'event_type' => $event->event_type,
                    'remarks' => $event->remarks,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $locLabel = $location ? "{$location->name} ({$location->code})" : 'Titik Rute';
            $this->notificationService->notifyParties(
                $shipment,
                'TRACKING_UPDATE',
                "Update {$event->event_type} ({$shipment->tracking_number})",
                "Kargo tercatat di {$locLabel}. " . ($event->remarks ? $event->remarks : "Status: {$shipment->status}.")
            );

            return $event;
        });
    }
}
