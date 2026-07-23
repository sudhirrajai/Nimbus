<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
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

    /**
     * Get auto-cleanup settings for activity logs
     */
    public function getSettings()
    {
        try {
            $retentionDays = Setting::where('key', 'activity_log_retention_days')->value('value') ?? '30';
            $autoClean = Setting::where('key', 'activity_log_auto_clean')->value('value') ?? '1';

            return response()->json([
                'success' => true,
                'retention_days' => (int) $retentionDays,
                'auto_clean' => $autoClean === '1',
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to fetch activity cleanup settings: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update auto-cleanup settings
     */
    public function updateSettings(Request $request)
    {
        $request->validate([
            'retention_days' => 'required|integer|min:0|max:365',
            'auto_clean' => 'required|boolean',
        ]);

        try {
            Setting::updateOrCreate(
                ['key' => 'activity_log_retention_days'],
                ['value' => (string) $request->input('retention_days')]
            );

            Setting::updateOrCreate(
                ['key' => 'activity_log_auto_clean'],
                ['value' => $request->input('auto_clean') ? '1' : '0']
            );

            ActivityLog::log('update', 'settings', "Updated activity log retention to {$request->input('retention_days')} day(s) (Auto-clean: " . ($request->input('auto_clean') ? 'Enabled' : 'Disabled') . ")");

            return response()->json([
                'success' => true,
                'message' => 'Activity log retention settings updated successfully.',
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to update activity cleanup settings: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Trigger manual activity log cleanup now
     */
    public function cleanNow(Request $request)
    {
        try {
            $days = $request->input('days');
            if ($days !== null) {
                $days = (int) $days;
            } else {
                $days = (int) (Setting::where('key', 'activity_log_retention_days')->value('value') ?? 30);
            }

            if ($days <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Retention period must be greater than 0 days to delete old logs.',
                ], 400);
            }

            $cutoff = now()->subDays($days);
            $deletedCount = ActivityLog::where('created_at', '<', $cutoff)->delete();

            ActivityLog::log('delete', 'activities', "Manually purged {$deletedCount} activity log(s) older than {$days} day(s).");

            return response()->json([
                'success' => true,
                'deleted_count' => $deletedCount,
                'message' => "Successfully purged {$deletedCount} activity log(s) older than {$days} day(s).",
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to execute manual activity log cleanup: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete all activity logs
     */
    public function deleteAll()
    {
        try {
            $count = ActivityLog::count();
            ActivityLog::query()->delete();

            ActivityLog::log('delete', 'activities', "Manually cleared all activity logs ({$count} records purged).");

            return response()->json([
                'success' => true,
                'deleted_count' => $count,
                'message' => "Successfully cleared all {$count} activity log records.",
            ]);
        } catch (\Exception $e) {
            \Log::error("Failed to delete all activity logs: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}
