<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostPetReport;
use App\Models\LostPetReportUpdate;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * Advanced administration statistics.
     */
    public function stats(): JsonResponse
    {
        $totalUsers        = User::count();
        $activeUsers       = User::whereHas('lostPetReports')->count();
        $bannedUsers       = User::where('is_banned', true)->count();
        $totalReports      = LostPetReport::count();
        $activeReports     = LostPetReport::where('status', 'active')->count();
        $rescuedPets       = LostPetReport::where('status', 'found')->count();
        $cancelledReports  = LostPetReport::where('status', 'cancelled')->count();
        $reportsToday      = LostPetReport::whereDate('created_at', today())->count();
        $rescuesThisMonth  = LostPetReport::where('status', 'found')
                               ->whereMonth('updated_at', now()->month)->count();

        // Top rescatadores — usuarios con más mascotas encontradas
        $topRescuers = User::select('users.id', 'users.full_name', 'users.email')
            ->selectRaw('COUNT(lost_pet_reports.id) as rescued_count')
            ->join('lost_pet_reports', 'users.id', '=', 'lost_pet_reports.user_id')
            ->where('lost_pet_reports.status', 'found')
            ->groupBy('users.id', 'users.full_name', 'users.email')
            ->orderByDesc('rescued_count')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas de administración',
            'data'    => [
                'users' => [
                    'total'   => $totalUsers,
                    'active'  => $activeUsers,
                    'banned'  => $bannedUsers,
                ],
                'reports' => [
                    'total'      => $totalReports,
                    'active'     => $activeReports,
                    'rescued'    => $rescuedPets,
                    'cancelled'  => $cancelledReports,
                    'today'      => $reportsToday,
                    'this_month' => $rescuesThisMonth,
                ],
                'top_rescuers' => $topRescuers,
            ],
        ]);
    }

    /**
     * List all users with report metrics.
     */
    public function users(): JsonResponse
    {
        $users = User::select('id', 'full_name', 'email', 'role', 'is_banned', 'created_at')
            ->withCount([
                'lostPetReports as total_reports',
                'lostPetReports as rescued_count' => function ($q) {
                    $q->where('status', 'found');
                },
                'lostPetReports as active_reports' => function ($q) {
                    $q->where('status', 'active');
                },
            ])
            ->orderByDesc('rescued_count')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Lista de usuarios',
            'data'    => $users,
        ]);
    }

    /**
     * Admin-cancel any report regardless of ownership.
     */
    public function deleteReport(string $id): JsonResponse
    {
        $report = LostPetReport::findOrFail($id);

        $oldStatus = $report->getOriginal('status');

        $report->update(['status' => 'cancelled']);

        LostPetReportUpdate::create([
            'report_id'  => $report->id,
            'user_id'    => auth()->id(),
            'old_status' => $oldStatus,
            'new_status' => 'cancelled',
            'notes'      => 'Cancelado por administrador',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reporte cancelado por administrador',
            'data'    => null,
        ]);
    }
}
