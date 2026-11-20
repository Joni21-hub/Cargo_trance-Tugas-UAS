<?php

namespace App\Http\Controllers\Api;

use App\Models\Location;
use App\Services\WeatherService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WeatherController extends BaseApiController
{
    public function __construct(
        protected WeatherService $weatherService
    ) {}

    /**
     * GET /api/v1/weather
     * Query live weather for coordinates or location_id.
     */
    public function show(Request $request): JsonResponse
    {
        if ($request->filled('location_id')) {
            $location = Location::find($request->query('location_id'));
            if (!$location instanceof Location) {
                return $this->sendError('Location not found', null, 404);
            }
            $weather = $this->weatherService->getWeatherForLocation($location);
            return $this->sendResponse($weather, 'Live weather retrieved for ' . $location->name);
        }

        $lat = (float) $request->query('lat', -6.1018);
        $lng = (float) $request->query('lng', 106.8836);

        $weather = $this->weatherService->getWeather($lat, $lng);
        return $this->sendResponse($weather, 'Live weather retrieved');
    }
}
