<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Wish;
use App\Models\Contribution;
use App\Services\PaymentService;
use App\Rules\RutValid;
use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class CheckoutController extends Controller
{
    protected PaymentService $paymentService;

    public function __construct(PaymentService $paymentService)
    {
        $this->paymentService = $paymentService;
    }

    /**
     * Check status of a payment by Flow token.
     */
    public function checkStatus(Request $request)
    {
        $token = $request->query('token');

        if (!$token) {
            return response()->json(['message' => 'Token required'], 400);
        }

        $statusData = $this->paymentService->getFlowStatus($token);

        if (!$statusData) {
            return response()->json(['message' => 'Status not found'], 404);
        }

        // If it's already paid, let's make sure it's processed in our side too
        if ($statusData['status'] == 2) {
            $commerceOrder = $statusData['commerceOrder'] ?? null;
            Log::info("Self-healing check: order " . $commerceOrder);
            if ($commerceOrder && str_starts_with($commerceOrder, 'CONT-')) {
                $orderParts = explode('-', str_replace('CONT-', '', $commerceOrder));
                $id = $orderParts[0] ?? null;
                $contribution = Contribution::find($id);
                if ($contribution) {
                    if ($contribution->status !== 'completed') {
                        Log::info("Found pending contribution $id, processing now...");
                        $paymentMethod = $statusData['paymentData']['media'] ?? 'Unknown';
                        $this->paymentService->processSuccessfulPayment(
                            $contribution, 
                            (string)($statusData['flowOrder'] ?? 'Unknown'),
                            $paymentMethod
                        );
                    } else {
                        Log::info("Contribution $id already completed.");
                    }
                } else {
                    Log::error("Contribution $id NOT FOUND in database.");
                }
            }
        }

        return response()->json([
            'status' => $statusData['status'], // 1: pend, 2: paid, 3: rejected, 4: cancelled
            'amount' => $statusData['amount'],
            'commerceOrder' => $statusData['commerceOrder']
        ]);
    }

    public function process(Request $request)
    {
        $minAmount = (int)Setting::where('key', 'min_contribution_amount')->first()?->value ?? 1000;

        $validated = $request->validate([
            'wish_id' => 'required|exists:wishes,id',
            'donor_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'rut' => ['required', 'string', new RutValid()],
            'amount' => "required|integer|min:$minAmount", 
            'gateway' => 'required|in:flow,mercadopago,transfer',
        ]);

        // Check if gateway is enabled
        $gatewayKey = 'enable_' . $validated['gateway'];
        $isEnabled = Setting::where('key', $gatewayKey)->first()?->value ?? '0';
        
        if ($isEnabled !== '1') {
            return response()->json(['message' => 'Este medio de pago no está habilitado actualmente.'], 403);
        }

        $wish = Wish::findOrFail($validated['wish_id']);

        if ($wish->event->status !== 'approved') {
            return response()->json(['message' => 'Evento no disponible'], 403);
        }

        $contribution = Contribution::create([
            'wish_id' => $wish->id,
            'donor_name' => $validated['donor_name'],
            'email' => $validated['email'],
            'rut' => $validated['rut'],
            'amount' => $validated['amount'],
            'status' => 'pending',
            'payment_method' => $validated['gateway'] === 'transfer' ? 'transfer' : $validated['gateway'],
        ]);

        if ($validated['gateway'] === 'flow') {
            $paymentUrl = $this->paymentService->createFlowPayment($contribution);
        } elseif ($validated['gateway'] === 'mercadopago') {
            $paymentUrl = $this->paymentService->createMercadoPagoPayment($contribution);
        } else {
            // Transfer doesn't have an external URL
            return response()->json(['success' => true, 'method' => 'transfer']);
        }

        return response()->json(['url' => $paymentUrl]);
    }

    /**
     * Get dynamic calculation for fees.
     */
    public function calculate(Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:0',
            'gateway' => 'required|in:flow,mercadopago,transfer',
            'type' => 'required|in:liquid,gross'
        ]);

        $gatewayKey = $validated['gateway'] === 'mercadopago' ? 'mp' : ($validated['gateway'] === 'transfer' ? 'transfer' : 'flow');

        if ($validated['type'] === 'liquid') {
            $data = $this->paymentService->calculateGrossFromLiquid($validated['amount'], $gatewayKey);
        } else {
            $data = $this->paymentService->calculateFeesFromGross($validated['amount'], $gatewayKey);
        }

        $data['min_contribution_amount'] = (int)Setting::where('key', 'min_contribution_amount')->first()?->value ?? 1000;

        return response()->json($data);
    }

    /**
     * Intermediate redirector to handle Flow's POST and redirect to Nuxt's GET.
     */
    public function handleResult(Request $request, string $uuid)
    {
        $token = $request->input('token');
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
        
        Log::info("Redirecting Flow result for UUID $uuid with token $token");
        
        return redirect()->to($frontendUrl . '/evento/' . $uuid . '?token=' . $token);
    }
}
