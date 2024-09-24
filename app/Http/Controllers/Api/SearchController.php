<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Apartment;
use App\Models\Service;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class SearchController extends Controller
{
    /**
     * Cerca appartamenti basati su latitudine e longitudine di una città e filtra in base al raggio.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function search(Request $request)
    {
        $request->validate([
            'query' => 'required|string|max:255',
            'radius' => 'nullable|numeric|min:1',
            'min_rooms' => 'nullable|integer|min:1',
            'services' => 'nullable|array',
        ]);

        $query = $request->input('query');
        $radius = $request->input('radius', 20);
        $minRooms = $request->input('min_rooms', 1);
        $services = $request->input('services', []);

        try {
            $client = new Client(['verify' => false]);
            $apiKey = env('TOMTOM_API_KEY');
            $response = $client->get("https://api.tomtom.com/search/2/geocode/{$query}.json", [
                'query' => [
                    'view' => 'Unified',
                    'key' => $apiKey
                ]
            ]);

            $data = json_decode($response->getBody(), true);

            if (!isset($data['results'][0])) {
                return response()->json(['error' => 'Località non trovata'], 404);
            }

            $firstLat = $data['results'][0]['position']['lat'];
            $firstLng = $data['results'][0]['position']['lon'];
        } catch (\Exception $e) {
            Log::error('Errore nella chiamata a TomTom: ' . $e->getMessage());
            return response()->json(['error' => 'Errore nel recupero delle coordinate'], 500);
        }

        $apartmentsQuery = Apartment::select('id', 'title', 'address', 'img', 'latitude', 'longitude', 'rooms', 'beds', 'bathrooms', 'mq', 'is_available', 'user_id')
            ->where('is_available', true)
            ->where('rooms', '>=', $minRooms)
            ->with(['user', 'services']);

        if (!empty($services)) {
            $apartmentsQuery->whereHas('services', function ($q) use ($services) {
                $q->whereIn('name', $services);
            });
        }

        $apartments = $apartmentsQuery->get();

        $filteredApartments = $apartments->filter(function ($apartment) use ($firstLat, $firstLng, $radius) {
            $distance = $this->calculateDistance($firstLat, $firstLng, $apartment->latitude, $apartment->longitude);
            return $distance <= $radius;
        })->values();

        $apartmentsArray = $filteredApartments->map(function ($apartment) {
            return [
                'id' => $apartment->id,
                'title' => $apartment->title,
                'address' => $apartment->address,
                'img' => $apartment->img,
                'latitude' => $apartment->latitude,
                'longitude' => $apartment->longitude,
                'rooms' => $apartment->rooms,
                'beds' => $apartment->beds,
                'bathrooms' => $apartment->bathrooms,
                'mq' => $apartment->mq,
                'is_available' => $apartment->is_available,
                'user' => [
                    'name' => $apartment->user->name,
                    'surname' => $apartment->user->surname,
                ],
                'services' => $apartment->services->pluck('name')->toArray(),
            ];
        })->toArray();

        return response()->json($apartmentsArray);
    }

    /**
     * Calcola la distanza tra due coordinate geografiche utilizzando la formula dell'haversine.
     *
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadius = 6371;

        $latFrom = deg2rad($lat1);
        $lngFrom = deg2rad($lng1);
        $latTo = deg2rad($lat2);
        $lngTo = deg2rad($lng2);

        $latDelta = $latTo - $latFrom;
        $lngDelta = $lngTo - $lngFrom;

        $angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) + cos($latFrom) * cos($latTo) * pow(sin($lngDelta / 2), 2)));

        return $earthRadius * $angle;
    }

    /**
     * Restituisce tutti i servizi disponibili.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableServices()
    {
        try {
            $services = Service::select('id', 'name')->get();
            return response()->json($services);
        } catch (\Exception $e) {
            Log::error('Errore nel recupero dei servizi: ' . $e->getMessage());
            return response()->json(['error' => 'Errore nel recupero dei servizi'], 500);
        }
    }
}
