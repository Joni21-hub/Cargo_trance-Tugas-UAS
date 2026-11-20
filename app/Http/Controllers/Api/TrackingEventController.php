<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\StoreTrackingEventRequest;
use App\Models\Shipment;
use App\Services\TrackingEventService;
use Illuminate\Http\JsonResponse;

class TrackingEventController extends BaseApiController
{
    public function __construct(
        protected TrackingEventService $eventService
    ) {}

    /**
     * POST /api/v1/shipments/{id}/tracking
     * Add checkpoint milestone to shipment.
     */
    public function store(StoreTrackingEventRequest $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $userId = (int) (auth()->id() ?? 1); // default to authenticated operator/admin or first user

        try {
            $event = $this->eventService->recordEvent($shipment, $request->validated(), $userId);
            return $this->sendResponse($event->load('location'), 'Tracking checkpoint recorded successfully', 201);
        } catch (\DomainException $e) {
            return $this->sendError($e->getMessage(), null, 422);
        } catch (\Throwable $e) {
            return $this->sendError('Failed to record tracking event: ' . $e->getMessage(), null, 500);
        }
    }
}
