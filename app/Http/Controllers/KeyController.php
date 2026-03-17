<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublicKeyRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeyController extends Controller
{
    /**
     * Store the authenticated user's public and encrypted private key
     */
    public function store(StorePublicKeyRequest $request): JsonResponse
    {
        $user = $request->user();

        $user->update([
            'public_key' => $request->public_key,
            'encrypted_private_key' => $request->encrypted_private_key,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Return the authenticated user's own keys (for multi-device restore)
     */
    public function showOwn(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'public_key' => $user->public_key,
            'encrypted_private_key' => $user->encrypted_private_key,
        ]);
    }

    /**
     * Return the public key of another user (for encrypting messages to them)
     */
    public function showPublic(Request $request, User $user): JsonResponse
    {
        if ($user->public_key === null) {
            abort(404, 'Public key not found for this user.');
        }

        return response()->json([
            'public_key' => $user->public_key,
        ]);
    }
}
