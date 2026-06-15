<?php

namespace App\Http\Controllers;

use App\Models\Store;
use App\Models\StoreOtpCode;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreOtpController extends Controller
{
    public function store(Request $r, Store $store)
    {
        $r->validate([
            'allowed_actions'  => 'required|array|min:1',
            'allowed_actions.*'=> 'string|in:revoke_api,rotate_secret,regenerate_api',
            'expires_in_hours' => 'nullable|integer|min:1|max:720',
        ]);

        $plain     = Str::random(32);
        $expiresAt = $r->filled('expires_in_hours')
            ? now()->addHours((int) $r->input('expires_in_hours'))
            : null;

        StoreOtpCode::create([
            'store_id'        => $store->id,
            'code_hash'       => hash('sha256', $plain),
            'allowed_actions' => $r->input('allowed_actions'),
            'expires_at'      => $expiresAt,
            'created_by'      => auth()->id(),
        ]);

        return redirect()
            ->route('stores.edit', $store)
            ->with('new_otp', $plain);
    }

    public function destroy(Store $store, StoreOtpCode $otp)
    {
        abort_unless($otp->store_id === $store->id, 404);
        $otp->delete();
        return back()->with('status', 'OTP revoked.');
    }

    public function regenerateKey(Store $store)
    {
        $store->update(['license_api_key' => Str::random(48)]);
        return back()->with('status', 'License API key regenerated.');
    }
}
