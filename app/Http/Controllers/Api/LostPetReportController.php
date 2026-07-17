<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostPetReport;
use App\Models\LostPetReportUpdate;
use App\Models\User;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LostPetReportController extends Controller
{
    /**
     * Calculate the distance between two coordinates using the Haversine formula.
     *
     * @param  float  $lat1  Latitude of point 1
     * @param  float  $lon1  Longitude of point 1
     * @param  float  $lat2  Latitude of point 2
     * @param  float  $lon2  Longitude of point 2
     * @return float Distance in kilometers
     */
    private function haversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R = 6371; // Earth's radius in km
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /**
     * Generate obfuscated coordinates within a percentage of the search radius.
     *
     * @param  float  $lat       Original latitude
     * @param  float  $lng       Original longitude
     * @param  int    $radiusKm  Search radius in kilometers
     * @return array{latitude: float, longitude: float}
     */
    private function obfuscateCoordinates(float $lat, float $lng, int $radiusKm = 5): array
    {
        // Desplazamiento aleatorio dentro del 60% del radio
        $maxOffset = ($radiusKm * 0.6) / 111.32;
        $offsetLat = (mt_rand(-1000, 1000) / 1000) * $maxOffset;
        $offsetLng = (mt_rand(-1000, 1000) / 1000) * $maxOffset / cos(deg2rad($lat));

        return [
            'latitude'  => round($lat + $offsetLat, 6),
            'longitude' => round($lng + $offsetLng, 6),
        ];
    }

    /**
     * Apply coordinate privacy to a report.
     *
     * The owner and admins see exact coordinates;
     * everyone else receives an obfuscated position.
     */
    private function applyPrivacy(LostPetReport $report, ?User $user): LostPetReport
    {
        // El dueño y el admin ven coordenadas exactas
        if ($user && ($user->id === $report->user_id || $user->role === 'admin')) {
            $report->is_exact_location = true;
            return $report;
        }

        // Todos los demás ven coordenadas ofuscadas
        $obfuscated = $this->obfuscateCoordinates(
            (float) $report->latitude,
            (float) $report->longitude,
            $report->radius_km ?? 5
        );

        $report->latitude  = $obfuscated['latitude'];
        $report->longitude = $obfuscated['longitude'];
        $report->is_exact_location = false;

        return $report;
    }

    /**
     * List paginated active reports with optional filters.
     *
     * Supports: ?species=dog, ?status=active, ?lat=X&lng=Y&radius=10 (km)
     */
    public function index(Request $request): JsonResponse
    {
        $query = LostPetReport::query()
            ->with([
                'user:id,full_name',
                'photos' => fn ($q) => $q->where('is_primary', true),
            ]);

        // Filter by species
        if ($request->filled('species')) {
            $query->where('species', $request->input('species'));
        }

        // Filter by status (default: only active)
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        } else {
            $query->where('status', 'active');
        }

        // Geolocation filter using Haversine formula
        if ($request->filled(['lat', 'lng', 'radius'])) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            $radius = (float) $request->input('radius'); // in km

            $query->whereRaw('
                (6371 * acos(
                    cos(radians(?)) *
                    cos(radians(latitude)) *
                    cos(radians(longitude) - radians(?)) +
                    sin(radians(?)) *
                    sin(radians(latitude))
                )) <= ?
            ', [$lat, $lng, $lat, $radius]);
        }

        $reports = $query->latest()->paginate(15);

        // Apply coordinate privacy based on authenticated user
        $user = auth('sanctum')->user();
        $reports->getCollection()->transform(function ($report) use ($user) {
            return $this->applyPrivacy($report, $user);
        });

        return response()->json([
            'success' => true,
            'message' => 'Listado de reportes',
            'data' => $reports,
        ]);
    }

    /**
     * Create a new lost pet report.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'pet_name' => 'nullable|string|max:255',
            'species' => 'required|string|max:50',
            'breed' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:255',
            'description' => 'required|string',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'location_description' => 'nullable|string|max:500',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|string|email|max:255',
            'lost_at' => 'nullable|date',
            'photo' => 'nullable|image|max:5120', // 5MB
            'has_reward'         => 'boolean',
            'reward_amount'      => 'nullable|numeric|min:0|max:9999',
            'reward_description' => 'nullable|string|max:255',
        ]);

        // Handle photo upload to Supabase Storage
        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $storageService = new SupabaseStorageService();
            $photoUrl = $storageService->uploadPhoto(
                $request->file('photo'),
                $request->user()->id
            );
        }

        $report = LostPetReport::create([
            'user_id' => $request->user()->id,
            'pet_name' => $validated['pet_name'] ?? null,
            'species' => $validated['species'],
            'breed' => $validated['breed'] ?? null,
            'color' => $validated['color'] ?? null,
            'description' => $validated['description'],
            'photo_url' => $photoUrl,
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'location_description' => $validated['location_description'] ?? null,
            'contact_phone' => $validated['contact_phone'] ?? null,
            'contact_email' => $validated['contact_email'] ?? null,
            'lost_at' => $validated['lost_at'] ?? null,
            'has_reward' => $validated['has_reward'] ?? false,
            'reward_amount' => $validated['reward_amount'] ?? null,
            'reward_description' => $validated['reward_description'] ?? null,
        ]);

        $report->load('user:id,full_name', 'photos');

        return response()->json([
            'success' => true,
            'message' => 'Reporte creado exitosamente',
            'data' => $report,
        ], 201);
    }

    /**
     * Show a single report with full details.
     */
    public function show(string $id): JsonResponse
    {
        $report = LostPetReport::with([
            'user:id,full_name',
            'photos',
            'updates',
        ])->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
                'data' => null,
            ], 404);
        }

        // Increment views counter directly in database
        DB::table('lost_pet_reports')
            ->where('id', $id)
            ->increment('views');

        // Refresh the model to get updated views count
        $report->refresh();

        // Apply coordinate privacy based on authenticated user
        $user = auth('sanctum')->user();
        $report = $this->applyPrivacy($report, $user);

        return response()->json([
            'success' => true,
            'message' => 'Detalle del reporte',
            'data' => $report,
        ]);
    }

    /**
     * Update a lost pet report (owner only).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $report = LostPetReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
                'data' => null,
            ], 404);
        }

        // Only the owner can update the report
        if ($report->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para editar este reporte',
                'data' => null,
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'nullable|string|in:active,found,cancelled,expired',
            'description' => 'nullable|string',
            'pet_name' => 'nullable|string|max:255',
            'contact_phone' => 'nullable|string|max:50',
            'contact_email' => 'nullable|string|email|max:255',
            'is_found' => 'nullable|boolean',
            'found_at' => 'nullable|date',
            'has_reward'         => 'boolean',
            'reward_amount'      => 'nullable|numeric|min:0|max:9999',
            'reward_description' => 'nullable|string|max:255',
        ]);

        $oldStatus = $report->status;

        // If is_found changes to true, auto-set status and found_at
        if (isset($validated['is_found']) && $validated['is_found'] && !$report->is_found) {
            $validated['status'] = $validated['status'] ?? 'found';
            $validated['found_at'] = $validated['found_at'] ?? now();

            // Register the status update
            LostPetReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'notes' => 'Mascota marcada como encontrada',
                'created_at' => now(),
            ]);
        } elseif (isset($validated['status']) && $validated['status'] !== $oldStatus) {
            // Register any other status change
            LostPetReportUpdate::create([
                'report_id' => $report->id,
                'user_id' => $request->user()->id,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'notes' => null,
                'created_at' => now(),
            ]);
        }

        $report->update($validated);
        $report->load('user:id,full_name', 'photos', 'updates');

        return response()->json([
            'success' => true,
            'message' => 'Reporte actualizado exitosamente',
            'data' => $report,
        ]);
    }

    /**
     * Soft-delete a report by setting status to cancelled (owner only).
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $report = LostPetReport::find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
                'data' => null,
            ], 404);
        }

        // Only the owner can delete the report
        if ($report->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para eliminar este reporte',
                'data' => null,
            ], 403);
        }

        $oldStatus = $report->status;

        $report->update(['status' => 'cancelled']);

        // Register the status change
        LostPetReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'old_status' => $oldStatus,
            'new_status' => 'cancelled',
            'notes' => 'Reporte eliminado por el usuario',
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reporte eliminado exitosamente',
            'data' => null,
        ]);
    }

    /**
     * Register a sighting/capture of a lost pet (Pokémon GO style).
     *
     * The capturer must be within the search radius and cannot be
     * the report owner. Creates an update log entry and optionally
     * uploads a sighting photo to Supabase Storage.
     */
    public function capture(Request $request, string $id): JsonResponse
    {
        $report = LostPetReport::with('user')->find($id);

        if (!$report) {
            return response()->json([
                'success' => false,
                'message' => 'Reporte no encontrado',
                'data' => null,
            ], 404);
        }

        // The report owner cannot capture their own pet
        if ($report->user_id === $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes reportar un avistamiento de tu propia mascota',
                'data' => null,
            ], 403);
        }

        // Report must be active
        if ($report->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Este reporte ya no está activo',
                'data' => null,
            ], 422);
        }

        // Validate capturer coordinates and optional photo
        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'photo' => 'nullable|image|max:5120', // 5MB
        ]);

        // Determine the search radius (from the associated alert or default 5 km)
        $radiusKm = 5;
        $alert = $report->alerts()->first();
        if ($alert && $alert->radius_km) {
            $radiusKm = $alert->radius_km;
        }

        // Verify proximity using the Haversine formula
        $distance = $this->haversineDistance(
            (float) $report->latitude,
            (float) $report->longitude,
            (float) $validated['latitude'],
            (float) $validated['longitude']
        );

        if ($distance > $radiusKm) {
            return response()->json([
                'success' => false,
                'message' => 'Debes estar más cerca de la zona de búsqueda para reportar un avistamiento',
                'data' => null,
            ], 422);
        }

        // Handle optional sighting photo upload
        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $storageService = new SupabaseStorageService();
            $file = $request->file('photo');
            $extension = $file->getClientOriginalExtension() ?: 'jpg';
            $fileName = "reports/{$report->id}/sightings/" . Str::uuid() . ".{$extension}";

            $supabaseUrl = rtrim(env('SUPABASE_URL', ''), '/');
            $serviceKey = env('SUPABASE_SERVICE_KEY', '');
            $bucket = env('SUPABASE_BUCKET', 'lost-pets');

            $uploadUrl = "{$supabaseUrl}/storage/v1/object/{$bucket}/{$fileName}";

            $response = \Illuminate\Support\Facades\Http::withToken($serviceKey)
                ->withHeaders(['Content-Type' => $file->getMimeType()])
                ->withBody($file->getContent(), $file->getMimeType())
                ->post($uploadUrl);

            if ($response->successful()) {
                $photoUrl = "{$supabaseUrl}/storage/v1/object/public/{$bucket}/{$fileName}";
            }
        }

        // Create the sighting update record
        $capturerName = $request->user()->full_name;
        $lat = $validated['latitude'];
        $lng = $validated['longitude'];

        $update = LostPetReportUpdate::create([
            'report_id' => $report->id,
            'user_id' => $request->user()->id,
            'old_status' => $report->status,
            'new_status' => $report->status, // Status does not change
            'notes' => "Avistamiento reportado por {$capturerName} en coordenadas {$lat}, {$lng}",
            'created_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '¡Avistamiento registrado! El dueño ha sido notificado.',
            'data' => [
                'update' => $update,
                'photo_url' => $photoUrl,
                'contact_phone' => $report->contact_phone,
            ],
        ]);
    }
}

