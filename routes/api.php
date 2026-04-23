<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\EventController;
use App\Http\Controllers\WishController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\GeographyController;
use App\Http\Controllers\EventReportController;
use App\Http\Controllers\WishTemplateController;
use App\Http\Controllers\BankController;
use App\Http\Controllers\ProfileController;

// Geography Public Routes
Route::get('/regions', [GeographyController::class, 'regions']);
Route::get('/cities', [GeographyController::class, 'cities']);

// Auth Routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Email Verification (Signed URL)
Route::get('/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
    ->middleware('signed')
    ->name('verification.verify');

// Protected Routes (Creator Dashboard)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/email/resend', [AuthController::class, 'resendVerification']);
    Route::get('/events', [EventController::class, 'index']);
    Route::post('/events', [EventController::class, 'store']);
    Route::put('/events/{event}', [EventController::class, 'update']);
    Route::delete('/events/{event}', [EventController::class, 'destroy']);
    
    Route::apiResource('wishes', WishController::class);

    // Admin Specific
    Route::get('/admin/stats', [AdminController::class, 'stats']);
    Route::get('/admin/users', [AdminController::class, 'users']);
    Route::put('/admin/users/{id}', [AdminController::class, 'updateUser']);
    Route::delete('/admin/users/{id}', [AdminController::class, 'destroyUser']);
    Route::get('/admin/payments', [AdminController::class, 'payments']);
    Route::get('/admin/settings', [AdminController::class, 'getSettings']);
    Route::post('/admin/settings', [AdminController::class, 'updateSettings']);
    Route::get('/admin/events/{id}/manual-payments', [AdminController::class, 'getEventManualPayments']);
    Route::post('/admin/events/{id}/manual-payments', [AdminController::class, 'storeEventManualPayment']);
    Route::get('/admin/events/{id}/pending-transfers', [AdminController::class, 'getPendingTransfers']);
    Route::put('/admin/contributions/{id}/approve-transfer', [AdminController::class, 'approveTransfer']);
    Route::apiResource('admin/categories', CategoryController::class);
    Route::get('/admin/reports', [EventReportController::class, 'index']);
    Route::put('/admin/reports/{id}/review', [EventReportController::class, 'review']);

    // Wish Templates
    Route::apiResource('admin/wish-templates', WishTemplateController::class);
    Route::get('/wish-templates', [WishTemplateController::class, 'publicIndex']);

    // Banks & Account Types Admin
    Route::get('/admin/banks', [BankController::class, 'index']);
    Route::post('/admin/banks', [BankController::class, 'store']);
    Route::put('/admin/banks/{id}', [BankController::class, 'update']);
    Route::delete('/admin/banks/{id}', [BankController::class, 'destroy']);
    Route::get('/admin/account-types', [BankController::class, 'accountTypes']);
    Route::post('/admin/account-types', [BankController::class, 'storeAccountType']);

    // Payouts Admin
    Route::get('/admin/payouts', [AdminController::class, 'payouts']);
    Route::post('/admin/payouts/{userId}/complete', [AdminController::class, 'completePayout']);

    // User Profile
    Route::put('/user/profile', [ProfileController::class, 'update']);
    Route::put('/user/password', [ProfileController::class, 'updatePassword']);
    Route::get('/user/payouts', [ProfileController::class, 'payoutSummary']);
});

// Public List for registration/profile
Route::get('/banks', [BankController::class, 'publicIndex']);

// Categories Public List
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/settings/public', [AdminController::class, 'getPublicSettings']);

// Public Event Endpoint
Route::get('/events/{uuid}', [EventController::class, 'show']);
Route::post('/events/{uuid}/report', [EventReportController::class, 'store']);

// Public Checkout Endpoint
Route::post('/checkout', [CheckoutController::class, 'process']);
Route::get('/checkout/calculate', [CheckoutController::class, 'calculate']);
Route::get('/checkout/status', [CheckoutController::class, 'checkStatus']);
Route::match(['get', 'post'], '/payment/result/{uuid}', [CheckoutController::class, 'handleResult']);

// Webhooks
Route::post('/webhooks/flow', [PaymentWebhookController::class, 'flow']);
