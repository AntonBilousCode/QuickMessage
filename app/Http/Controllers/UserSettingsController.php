<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class UserSettingsController extends Controller
{
    /**
     * Show the settings page
     */
    public function index(Request $request): View
    {
        return view('settings.index', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Toggle E2EE for the authenticated user
     */
    public function updateE2ee(Request $request): JsonResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $enabled = (bool) $request->enabled;

        $data = ['e2ee_enabled' => $enabled];

        if (! $enabled) {
            $data['public_key'] = null;
            $data['encrypted_private_key'] = null;
        }

        $user->update($data);

        Log::info('[Settings] E2EE toggled', [
            'user_id' => $user->id,
            'enabled' => $enabled,
        ]);

        return response()->json([
            'ok' => true,
            'e2ee_enabled' => $enabled,
        ]);
    }
}
