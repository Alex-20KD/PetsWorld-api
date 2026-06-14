<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostPetReport;
use App\Models\LostPetReportUpdate;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LostPetReportController extends Controller
{
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
}
