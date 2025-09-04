<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected $apiKey;
    protected $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openweather.api_key', 'demo_key');
        $this->baseUrl = 'https://api.openweathermap.org/data/2.5';
    }

    public function getWeatherByLocation(string $location): ?array
    {
        // Cache weather data for 30 minutes
        $cacheKey = 'weather_' . md5($location);
        
        return Cache::remember($cacheKey, 1800, function () use ($location) {
            try {
                // First get coordinates from location name
                $geoResponse = Http::get("{$this->baseUrl}/weather", [
                    'q' => $location,
                    'appid' => $this->apiKey,
                    'units' => 'metric'
                ]);

                if (!$geoResponse->successful()) {
                    return $this->getFallbackWeather($location);
                }

                $data = $geoResponse->json();

                return [
                    'location' => $data['name'] . ', ' . $data['sys']['country'],
                    'temperature' => round($data['main']['temp']),
                    'description' => ucfirst($data['weather'][0]['description']),
                    'icon' => $data['weather'][0]['icon'],
                    'humidity' => $data['main']['humidity'],
                    'wind_speed' => round($data['wind']['speed'] * 3.6), // Convert m/s to km/h
                    'feels_like' => round($data['main']['feels_like']),
                    'pressure' => $data['main']['pressure'],
                    'visibility' => isset($data['visibility']) ? round($data['visibility'] / 1000, 1) : null,
                ];

            } catch (\Exception $e) {
                Log::error('Weather API Error: ' . $e->getMessage());
                return $this->getFallbackWeather($location);
            }
        });
    }

    public function getWeatherByCoordinates(float $lat, float $lon): ?array
    {
        $cacheKey = 'weather_coords_' . md5($lat . '_' . $lon);
        
        return Cache::remember($cacheKey, 1800, function () use ($lat, $lon) {
            try {
                $response = Http::get("{$this->baseUrl}/weather", [
                    'lat' => $lat,
                    'lon' => $lon,
                    'appid' => $this->apiKey,
                    'units' => 'metric'
                ]);

                if (!$response->successful()) {
                    return $this->getFallbackWeather('Current Location');
                }

                $data = $response->json();

                return [
                    'location' => $data['name'] . ', ' . $data['sys']['country'],
                    'temperature' => round($data['main']['temp']),
                    'description' => ucfirst($data['weather'][0]['description']),
                    'icon' => $data['weather'][0]['icon'],
                    'humidity' => $data['main']['humidity'],
                    'wind_speed' => round($data['wind']['speed'] * 3.6),
                    'feels_like' => round($data['main']['feels_like']),
                    'pressure' => $data['main']['pressure'],
                    'visibility' => isset($data['visibility']) ? round($data['visibility'] / 1000, 1) : null,
                ];

            } catch (\Exception $e) {
                Log::error('Weather API Error: ' . $e->getMessage());
                return $this->getFallbackWeather('Current Location');
            }
        });
    }

    public function getForecast(string $location, int $days = 5): ?array
    {
        $cacheKey = 'forecast_' . md5($location . '_' . $days);
        
        return Cache::remember($cacheKey, 3600, function () use ($location, $days) {
            try {
                $response = Http::get("{$this->baseUrl}/forecast", [
                    'q' => $location,
                    'appid' => $this->apiKey,
                    'units' => 'metric',
                    'cnt' => $days * 8 // 8 forecasts per day (every 3 hours)
                ]);

                if (!$response->successful()) {
                    return null;
                }

                $data = $response->json();
                $forecasts = [];

                foreach ($data['list'] as $forecast) {
                    $forecasts[] = [
                        'date' => $forecast['dt_txt'],
                        'temperature' => round($forecast['main']['temp']),
                        'description' => ucfirst($forecast['weather'][0]['description']),
                        'icon' => $forecast['weather'][0]['icon'],
                        'humidity' => $forecast['main']['humidity'],
                        'wind_speed' => round($forecast['wind']['speed'] * 3.6),
                    ];
                }

                return [
                    'location' => $data['city']['name'] . ', ' . $data['city']['country'],
                    'forecasts' => $forecasts
                ];

            } catch (\Exception $e) {
                Log::error('Weather Forecast API Error: ' . $e->getMessage());
                return null;
            }
        });
    }

    private function getFallbackWeather(string $location): array
    {
        // Return mock weather data when API is not available
        return [
            'location' => $location,
            'temperature' => 28,
            'description' => 'Partly Cloudy',
            'icon' => '02d',
            'humidity' => 65,
            'wind_speed' => 15,
            'feels_like' => 30,
            'pressure' => 1013,
            'visibility' => 10.0,
            'is_fallback' => true
        ];
    }

    public function getWeatherIcon(string $iconCode): string
    {
        return "https://openweathermap.org/img/wn/{$iconCode}@2x.png";
    }
}
