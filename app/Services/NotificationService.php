<?php

namespace App\Services;

use App\Models\AppNotification;
use App\Models\Shipment;

class NotificationService
{
    /**
     * Create an in-app notification for a specific user.
     */
    public function notify(
        ?int $userId,
        ?int $shipmentId,
        string $type,
        string $title,
        string $message
    ): ?AppNotification {
        if (!$userId) {
            return null;
        }

        return AppNotification::create([
            'user_id' => $userId,
            'shipment_id' => $shipmentId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'is_read' => false,
        ]);
    }

    /**
     * Notify both shipper (customer) and buyer (consignee) if distinct users.
     */
    public function notifyParties(Shipment $shipment, string $type, string $title, string $message): void
    {
        $ids = array_unique(array_filter([
            $shipment->customer_id,
            $shipment->buyer_id,
        ]));

        foreach ($ids as $uid) {
            $this->notify((int) $uid, $shipment->id, $type, $title, $message);
        }
    }
}
