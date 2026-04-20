<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\PaymentService;
use App\Models\Contribution;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    protected $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Handle Flow's asynchronous confirmation notification.
     */
    public function flow(Request $request)
    {
        $token = $request->input('token');

        if (!$token) {
            return response()->json(['message' => 'Token not provided'], 400);
        }

        Log::info("Flow Webhook received for token: " . $token);

        $statusData = $this->paymentService->getFlowStatus($token);

        if (!$statusData) {
            return response()->json(['message' => 'Could not verify status with Flow'], 500);
        }

        // commerceOrder was set as 'CONT-' . $contribution->id
        $commerceOrder = $statusData['commerceOrder'] ?? null;
        if (!$commerceOrder || !str_starts_with($commerceOrder, 'CONT-')) {
            Log::error("Invalid commerceOrder from Flow: " . $commerceOrder);
            return response()->json(['message' => 'Invalid order format'], 400);
        }

        $orderParts = explode('-', str_replace('CONT-', '', $commerceOrder));
        $contributionId = $orderParts[0] ?? null;
        $contribution = Contribution::find($contributionId);

        if (!$contribution) {
            Log::error("Contribution not found: " . $contributionId);
            return response()->json(['message' => 'Contribution not found'], 404);
        }

        // Status 2 = Pagada
        if ($statusData['status'] == 2) {
            $paymentMethod = $statusData['paymentData']['media'] ?? 'Unknown';
            $this->paymentService->processSuccessfulPayment(
                $contribution, 
                $statusData['flowOrder'] ?? 'Unknown',
                $paymentMethod
            );
            return response()->json(['message' => 'Payment processed successfully']);
        }

        return response()->json(['message' => 'Payment not successful', 'status' => $statusData['status']]);
    }
}
