<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    /**
     * Display activity log page
     */
    public function index()
    {
        return Inertia::render('Activities/Index');
    }

    /**
     * Get paginated and filtered activity logs
     */
    public function getActivities(Request $request)
    {
        try {
            $query = ActivityLog::with('user')->orderBy('created_at', 'desc');

            // Search filter
            if ($search = $request->input('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhere('ip_address', 'like', "%{$search}%");
                });
            }

            // Service filter
            if ($service = $request->input('service')) {
                $query->where('service', $service);
            }

            // Action filter
            if ($action = $request->input('action')) {
                $query->where('action', $action);
            }

            // Date filter (last 24h, last 7 days, last 30 days)
            if ($dateRange = $request->input('date_range')) {
                $date = match ($dateRange) {
                    '24h' => now()->subDay(),
                    '7d' => now()->subDays(7),
                    '30d' => now()->subDays(30),
                    default => null,
                };
                if ($date) {
                    $query->where('created_at', '>=', $date);
                }
            }

            $activities = $query->paginate(25);

            // Extract unique services and actions in the system for filter options
            $services = ActivityLog::select('service')->distinct()->pluck('service');
            $actions = ActivityLog::select('action')->distinct()->pluck('action');

            return response()->json([
                'success' => true,
                'activities' => $activities,
                'services' => $services,
                'actions' => $actions,
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch activity logs: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
