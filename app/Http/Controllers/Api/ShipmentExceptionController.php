<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\ResolveShipmentExceptionRequest;
use App\Http\Requests\StoreShipmentExceptionRequest;
use App\Models\Shipment;
use App\Models\ShipmentException;
use App\Services\ExceptionManagementService;
use Illuminate\Http\JsonResponse;

class ShipmentExceptionController extends BaseApiController
{
    public function __construct(
        protected ExceptionManagementService $exceptionService
    ) {}

    /**
     * GET /api/v1/shipments/{id}/exceptions
     */
    public function index(string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $exceptions = $shipment->exceptions()->with('trackingEvent')->get();

        return $this->sendResponse($exceptions, 'Shipment exceptions retrieved successfully');
    }

    /**
     * POST /api/v1/shipments/{id}/exceptions
     * Log new delay exception for shipment.
     */
    public function store(StoreShipmentExceptionRequest $request, string $id): JsonResponse
    {
        $shipment = Shipment::where('id', $id)
            ->orWhere('tracking_number', $id)
            ->first();

        if (!$shipment) {
            return $this->sendError('Shipment not found', null, 404);
        }

        $userId = (int) (auth()->id() ?? 1);

        $exception = $this->exceptionService->recordException($shipment, $request->validated(), $userId);

        return $this->sendResponse([
            'exception' => $exception,
            'shipment' => $shipment->fresh(),
        ], 'Exception recorded and ETA recalculated', 201);
    }

    /**
     * POST /api/v1/exceptions/{id}/resolve
     */
    public function resolve(ResolveShipmentExceptionRequest $request, int $id): JsonResponse
    {
        $exception = ShipmentException::find($id);

        if (!$exception) {
            return $this->sendError('Exception not found', null, 404);
        }

        $userId = (int) (auth()->id() ?? 1);

        $resolved = $this->exceptionService->resolveException($exception, $request->validated('resolution_notes'), $userId);

        return $this->sendResponse([
            'exception' => $resolved,
            'shipment' => $resolved->shipment->fresh(),
        ], 'Exception resolved and ETA recalculated');
    }
}
