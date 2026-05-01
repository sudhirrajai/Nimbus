<?php

namespace App\Http\Controllers;

use App\Models\SecurityRule;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SecurityController extends Controller
{
    /**
     * Get security rules and settings
     */
    public function index()
    {
        $rules = SecurityRule::latest()->get();
        $mode = Setting::where('key', 'ip_restriction_mode')->first()?->value ?? 'off';
        $currentIp = request()->ip();

        return response()->json([
            'success' => true,
            'rules' => $rules,
            'mode' => $mode,
            'current_ip' => $currentIp
        ]);
    }

    /**
     * Store a new security rule
     */
    public function storeRule(Request $request)
    {
        $request->validate([
            'ip_address' => 'required|string', // Could be IP or CIDR
            'type' => 'required|in:allow,block',
            'description' => 'nullable|string|max:255',
        ]);

        SecurityRule::create([
            'ip_address' => $request->ip_address,
            'type' => $request->type,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Security rule added successfully'
        ]);
    }

    /**
     * Toggle rule active status
     */
    public function toggleRule(SecurityRule $rule)
    {
        $rule->update([
            'is_active' => !$rule->is_active
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Rule updated'
        ]);
    }

    /**
     * Delete a security rule
     */
    public function deleteRule(SecurityRule $rule)
    {
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Security rule deleted successfully'
        ]);
    }

    /**
     * Update IP restriction mode
     */
    public function updateMode(Request $request)
    {
        $request->validate([
            'mode' => 'required|in:off,whitelist,blacklist'
        ]);

        $ip = $request->ip();

        // Prevent lockout: if enabling whitelist, ensure current IP is allowed
        if ($request->mode === 'whitelist') {
            $rules = SecurityRule::where('type', 'allow')
                ->where('is_active', true)
                ->get();
            
            $isAllowed = false;
            foreach ($rules as $rule) {
                if ($this->ipMatches($ip, $rule->ip_address)) {
                    $isAllowed = true;
                    break;
                }
            }
            
            if (!$isAllowed && $ip !== '127.0.0.1' && $ip !== '::1') {
                return response()->json([
                    'success' => false,
                    'message' => "Cannot enable Whitelist mode. Your current IP ($ip) is not in the allowed list. Please add it first to avoid locking yourself out."
                ], 422);
            }
        }

        Setting::updateOrCreate(
            ['key' => 'ip_restriction_mode'],
            ['value' => $request->mode]
        );

        return response()->json([
            'success' => true,
            'message' => 'Security mode updated successfully'
        ]);
    }

    /**
     * Check if an IP matches a rule (supports exact match and CIDR)
     */
    private function ipMatches($ip, $ruleIp)
    {
        if ($ip === $ruleIp) {
            return true;
        }

        if (str_contains($ruleIp, '/')) {
            [$subnet, $bits] = explode('/', $ruleIp);
            $ipAddr = ip2long($ip);
            $subnetAddr = ip2long($subnet);
            $mask = -1 << (32 - $bits);
            $subnetAddr &= $mask;
            return ($ipAddr & $mask) == $subnetAddr;
        }

        return false;
    }
}
