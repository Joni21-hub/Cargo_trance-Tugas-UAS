<?php

namespace App\Services;

use App\Models\Shipment;
use DomainException;

class ShipmentLifecycleService
{
    /**
     * Allowed forward status transitions.
     */
    protected const ALLOWED_TRANSITIONS = [
        'CREATED' => ['CONFIRMED', 'BOOKED', 'IN_TRANSIT', 'CANCELLED'],
        'CONFIRMED' => ['IN_TRANSIT', 'DELIVERED', 'CANCELLED'],
        'BOOKED' => ['CONFIRMED', 'PICKED_UP', 'IN_TRANSIT', 'CANCELLED'],
        'PICKED_UP' => ['IN_TRANSIT', 'CANCELLED'],
        'IN_TRANSIT' => ['DELIVERED', 'CANCELLED'],
        'DELIVERED' => [],
        'CANCELLED' => [],
    ];

    /**
     * Validate and apply status transition on shipment.
     *
     * @throws DomainException
     */
    public function transition(Shipment $shipment, string $newStatus): Shipment
    {
        $currentStatus = $shipment->status;

        if ($currentStatus === $newStatus) {
            return $shipment;
        }

        $allowed = self::ALLOWED_TRANSITIONS[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowed, true)) {
            throw new DomainException(
                "Illegal shipment status transition from '{$currentStatus}' to '{$newStatus}'. Allowed: [" . implode(', ', $allowed) . "]."
            );
        }

        $shipment->status = $newStatus;
        $shipment->save();

        return $shipment;
    }

    /**
     * Check if transition is valid.
     */
    public function canTransition(Shipment $shipment, string $newStatus): bool
    {
        $allowed = self::ALLOWED_TRANSITIONS[$shipment->status] ?? [];
        return in_array($newStatus, $allowed, true);
    }
}
