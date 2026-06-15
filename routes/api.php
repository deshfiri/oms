<?php

use App\Http\Controllers\Api\CourierWebhookController;
use App\Http\Controllers\Api\OtpVerifyController;
use App\Http\Controllers\Api\WebhookReceiverController;
use Illuminate\Support\Facades\Route;

// Storefront → OMS (HMAC verified inside the controller)
Route::post('/webhooks/dfcommerce/{store}', [WebhookReceiverController::class, 'handle'])
    ->name('webhooks.dfcommerce');

// Courier → OMS  /api/webhooks/courier/{slug}
Route::post('/webhooks/courier/{slug}', [CourierWebhookController::class, 'handle'])
    ->name('webhooks.courier');

// License server — OTP verification endpoint called by client storefronts
Route::post('/verify-otp', [OtpVerifyController::class, 'verify'])
    ->name('api.verify-otp');
