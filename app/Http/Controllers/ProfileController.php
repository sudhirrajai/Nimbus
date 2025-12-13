<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class ProfileController extends Controller
{
    /**
     * Display profile page
     */
    public function index()
    {
        return Inertia::render('Profile/Index', [
            'user' => Auth::user()
        ]);
    }

    /**
     * Update profile information
     */
    public function updateProfile(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update([
            'name' => $request->name,
            'email' => $request->email,
        ]);

        return back()->with('success', 'Profile updated successfully');
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = Auth::user();

        // Verify current password
        if (!Hash::check($request->current_password, $user->password)) {
            return back()->withErrors([
                'current_password' => 'The current password is incorrect.'
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return back()->with('success', 'Password changed successfully');
    }

    /**
     * Display settings page
     */
    public function settings()
    {
        return Inertia::render('Settings/Index');
    }

    /**
     * Get panel settings
     */
    public function getSettings()
    {
        $settings = [
            'panel_name' => config('app.name', 'Nimbus'),
            'timezone' => config('app.timezone', 'UTC'),
            'auto_refresh' => true,
            'session_lifetime' => config('session.lifetime', 120),
        ];

        return response()->json([
            'success' => true,
            'settings' => $settings
        ]);
    }

    /**
     * Update panel settings
     */
    public function updateSettings(Request $request)
    {
        // For now, just return success
        // In a full implementation, we would save to a settings table or .env file
        return response()->json([
            'success' => true,
            'message' => 'Settings updated successfully'
        ]);
    }
}
