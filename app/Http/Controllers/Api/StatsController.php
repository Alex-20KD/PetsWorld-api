<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LostPetReport;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class StatsController extends Controller
{
    public function index(): JsonResponse
    {
        $stats = [
            'total_reports'  => LostPetReport::count(),
            'active_reports' => LostPetReport::where('status', 'active')->count(),
            'rescued_pets'   => LostPetReport::where('status', 'found')->count(),
            'total_users'    => User::where('is_banned', false)->count(),
        ];

        return response()->json([
            'success' => true,
            'message' => 'Estadísticas de PetsWorld',
            'data'    => $stats,
        ]);
    }
}
