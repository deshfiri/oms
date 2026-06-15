<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\StoreOtpCode;
use Illuminate\Http\Request;

class OtpVerifyController extends Controller
{
    public function verify(Request $request)
    {
        $apiKey = trim((string) $request->header('X-Api-Key', ''));
        if ($apiKey === '') {
            return response()->json(['success' => false], 401);
        }

        $store = Store::where('license_api_key', $apiKey)
            ->where('is_active', true)
            ->first();

        if (! $store) {
            return response()->json(['success' => false], 401);
        }

        $otp             = trim((string) $request->input('otp', ''));
        $requestedAction = trim((string) $request->input('requested_action', ''));

        if ($otp === '') {
            return response()->json(['success' => false]);
        }

        $code = StoreOtpCode::where('store_id', $store->id)
            ->where('code_hash', hash('sha256', $otp))
            ->whereNull('used_at')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->first();

        if (! $code) {
            return response()->json(['success' => false]);
        }

        // When the client specifies an action, it must be in the allowed list.
        if ($requestedAction !== '' && ! in_array($requestedAction, $code->allowed_actions ?? [], true)) {
            return response()->json(['success' => false]);
        }

        $code->update(['used_at' => now()]);

        return response()->json([
            'success'         => true,
            'allowed_actions' => $code->allowed_actions,
            'expires_at'      => $code->expires_at?->toIso8601String(),
        ]);
    }
}
