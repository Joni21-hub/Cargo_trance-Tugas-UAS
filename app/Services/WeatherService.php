<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * Get live marine and weather conditions from Open-Meteo for given coordinates.
     */
    public function getWeather(float $latitude, float $longitude): array
    {
        $cacheKey = "weather_{$latitude}_{$longitude}";

        return Cache::remember($cacheKey, 600, function () use ($latitude, $longitude) {
            try {
                $response = Http::timeout(5)->get('https://api.open-meteo.com/v1/forecast', [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,weather_code,wind_speed_10m,wind_direction_10m',
                    'timezone' => 'auto',
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $current = $data['current'] ?? [];
                    $weatherCode = $current['weather_code'] ?? 0;
                    $windSpeed = $current['wind_speed_10m'] ?? 0;

                    $condition = $this->interpretWeatherCode($weatherCode);
                    $riskLevel = $this->evaluateMaritimeRisk($weatherCode, $windSpeed);

                    return [
                        'source' => 'Open-Meteo Public Weather API',
                        'temperature_c' => $current['temperature_2m'] ?? null,
                        'humidity_percent' => $current['relative_humidity_2m'] ?? null,
                        'wind_speed_kmh' => $windSpeed,
                        'wind_direction_deg' => $current['wind_direction_10m'] ?? null,
                        'weather_code' => $weatherCode,
                        'condition' => $condition['text'],
                        'icon' => $condition['icon'],
                        'maritime_risk' => $riskLevel,
                        'is_storm_warning' => $riskLevel === 'HIGH',
                        'recorded_at' => $current['time'] ?? now()->toIso8601String(),
                    ];
                }
            } catch (\Throwable $e) {
                Log::warning('Weather API call failed: ' . $e->getMessage());
            }

            // Fallback estimation if network is slow/offline
            return [
                'source' => 'Fallback Weather Estimator',
                'temperature_c' => 28.5,
                'humidity_percent' => 75,
                'wind_speed_kmh' => 15.0,
                'weather_code' => 1,
                'condition' => 'Mainly Clear',
                'icon' => '🌤️',
                'maritime_risk' => 'NORMAL',
                'is_storm_warning' => false,
                'recorded_at' => now()->toIso8601String(),
            ];
        });
    }

    public function getWeatherForLocation(Location $location): array
    {
        $weather = $this->getWeather($location->latitude, $location->longitude);
        $weather['location_name'] = $location->name;
        $weather['location_code'] = $location->code;
        return $weather;
    }

    protected function interpretWeatherCode(int $code): array
    {
        return match (true) {
            $code === 0 => ['text' => 'Clear Sky', 'icon' => '☀️'],
            in_array($code, [1, 2, 3]) => ['text' => 'Partly Cloudy', 'icon' => '⛅'],
            in_array($code, [45, 48]) => ['text' => 'Fog / Mist', 'icon' => '🌫️'],
            in_array($code, [51, 53, 55, 61, 63, 65]) => ['text' => 'Rain Showers', 'icon' => '🌧️'],
            in_array($code, [71, 73, 75, 77]) => ['text' => 'Snow / Sleet', 'icon' => '❄️'],
            in_array($code, [80, 81, 82]) => ['text' => 'Heavy Rain', 'icon' => '⛈️'],
            in_array($code, [95, 96, 99]) => ['text' => 'Severe Thunderstorm', 'icon' => '🌩️'],
            default => ['text' => 'Overcast', 'icon' => '☁️'],
        };
    }

    protected function evaluateMaritimeRisk(int $weatherCode, float $windSpeed): string
    {
        if (in_array($weatherCode, [95, 96, 99]) || $windSpeed >= 50.0) {
            return 'HIGH'; // Severe marine hazard, likely causing WEATHER_DELAY
        }
        if (in_array($weatherCode, [80, 81, 82, 45, 48]) || $windSpeed >= 35.0) {
            return 'MODERATE';
        }
        return 'NORMAL';
    }
}
