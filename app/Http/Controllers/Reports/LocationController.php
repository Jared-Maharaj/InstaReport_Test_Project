<?php

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class LocationController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate(['address' => ['required', 'string', 'max:1000']]);
        $key = config('services.geoapify.key');
        $unavailable = 'Address lookup is unavailable. You can still enter the address and coordinates manually.';

        abort_unless($key, 503, $unavailable);

        try {
            $response = Http::acceptJson()->connectTimeout(3)->timeout(8)
                ->get('https://api.geoapify.com/v1/geocode/search', [
                    'text' => $data['address'],
                    'format' => 'json',
                    'limit' => 5,
                    'apiKey' => $key,
                ]);
        } catch (ConnectionException) {
            abort(503, $unavailable);
        }

        abort_unless($response->successful() && is_array($response->json('results')), 503, $unavailable);

        $locations = collect($response->json('results'))->filter(fn (mixed $location): bool => is_array($location) && is_string($location['formatted'] ?? null)
            && is_numeric($location['lat'] ?? null) && abs((float) $location['lat']) <= 90
            && is_numeric($location['lon'] ?? null) && abs((float) $location['lon']) <= 180
        )->take(5)->map(fn (array $location): array => [
            'address' => $location['formatted'],
            'latitude' => (float) $location['lat'],
            'longitude' => (float) $location['lon'],
        ])->values();

        return response()->json(['data' => $locations])->header('Cache-Control', 'no-store, private');
    }
}