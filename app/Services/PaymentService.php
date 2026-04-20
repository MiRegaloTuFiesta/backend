<?php

namespace App\Services;

use App\Models\Contribution;
use App\Models\Wish;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;
use GuzzleHttp\Client;

class PaymentService
{
    protected $apiKey;
    protected $secretKey;
    protected $baseUrl;
    protected $client;

    public function __construct()
    {
        $this->apiKey = config('services.flow.key');
        $this->secretKey = config('services.flow.secret');
        $this->baseUrl = config('services.flow.base_url', 'https://sandbox.flow.cl/api');
        $this->client = new Client();
    }
    /**
     * Process a confirmed payment webhook.
     */
    public function processSuccessfulPayment(Contribution $contribution, string $paymentGatewayId, ?string $paymentMethod = null)
    {
        if ($contribution->status === 'completed') {
            return; // Already processed
        }

        DB::transaction(function () use ($contribution, $paymentGatewayId, $paymentMethod) {
            // Recalcular comisiones basándose en el monto real pagado y la pasarela
            $gateway = $contribution->payment_method ? strtolower($contribution->payment_method) : 'flow';
            // Nota: El paymentMethod de Flow puede venir como "Webpay", "Mach", etc. 
            // Si es Flow, usamos los settings de flow.
            $gatewayKey = (str_contains($gateway, 'mercadopago')) ? 'mp' : 'flow';
            
            $fees = $this->calculateFeesFromGross($contribution->amount, $gatewayKey);

            // Update contribution status and recorded fees
            $contribution->status = 'completed';
            $contribution->payment_id = $paymentGatewayId;
            $contribution->payment_method = $paymentMethod;
            
            $contribution->platform_fee = $fees['platform_fee'];
            $contribution->gateway_fee = $fees['gateway_fee'];
            $contribution->net_to_user = $fees['net_amount'];
            
            $contribution->save();

            $wish = $contribution->wish;
            $event = $wish->event;

            // Increment wish amount and mark as completed (blocked)
            $wish->current_amount += $fees['net_amount'];
            $wish->status = 'completed'; 
            $wish->save();

            // Update event total collected
            $event->collected_amount += $fees['net_amount'];
            
            // Check overflow against Event total_price
            if ($event->total_price > 0 && $event->collected_amount >= $event->total_price) {
                $overflow = $event->collected_amount - $event->total_price;
                $event->overflow_balance = $overflow;
            }

            $event->save();
        });
    }

    /**
     * Create a Flow payment link.
     */
    public function createFlowPayment(Contribution $contribution)
    {
        $url = $this->baseUrl . '/payment/create';
        
        $params = [
            'apiKey' => $this->apiKey,
            'commerceOrder' => 'CONT-' . $contribution->id . '-' . $contribution->wish_id . '-' . $contribution->wish->event_id,
            'subject' => 'Aporte para regalo: ' . $contribution->wish->name,
            'currency' => 'CLP',
            'amount' => $contribution->amount,
            'email' => $contribution->email,
            'urlConfirmation' => url('/api/webhooks/flow'),
            'urlReturn' => url('/api/payment/result/' . $contribution->wish->event->uuid),
        ];

        $params['s'] = $this->signParams($params);

        try {
            $response = $this->client->post($url, [
                'form_params' => $params
            ]);

            $data = json_decode($response->getBody(), true);

            if (isset($data['url']) && isset($data['token'])) {
                return $data['url'] . '?token=' . $data['token'];
            }

            throw new \Exception('Flow Error: ' . ($data['message'] ?? 'Unknown error'));
        } catch (\Exception $e) {
            Log::error('Flow Payment Creation Failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get Flow Payment Status.
     */
    public function getFlowStatus(string $token)
    {
        $url = $this->baseUrl . '/payment/getStatus';
        
        $params = [
            'apiKey' => $this->apiKey,
            'token' => $token,
        ];

        $params['s'] = $this->signParams($params);

        try {
            $response = $this->client->get($url . '?' . http_build_query($params));
            return json_decode($response->getBody(), true);
        } catch (\Exception $e) {
            Log::error('Flow Status Check Failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Sign parameters for Flow.
     */
    private function signParams(array $params): string
    {
        ksort($params);
        $toSign = "";
        foreach ($params as $key => $value) {
            $toSign .= $key . $value;
        }
        return hash_hmac('sha256', $toSign, $this->secretKey);
    }

    /**
     * Create a MercadoPago preference link.
     */
    /**
     * Create a MercadoPago preference link.
     */
    public function createMercadoPagoPayment(Contribution $contribution)
    {
        // $mpToken = config('services.mercadopago.token');
        // Logic to generate Mercado Pago Preference
        // Mock returning a URL
        return 'https://mercadopago.cl/mock-payment/' . $contribution->id;
    }

    /**
     * Calculate Fees starting from a Liquid Amount (what creator wants to receive)
     * Returns the Gross Amount the guest must pay.
     */
    public function calculateGrossFromLiquid(int $liquid, string $gatewayKey = 'flow'): array
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        $p = (float)($settings['platform_fee_percent'] ?? 0) / 100;
        $f = (float)($settings["{$gatewayKey}_fee_percent"] ?? 0) / 100;
        $fixed = (float)($settings["{$gatewayKey}_fee_fixed"] ?? 0);
        $iva = (float)($settings['iva_percent'] ?? 0) / 100;

        if ($gatewayKey === 'transfer') {
            $denominator = 1 - $p;
            if ($denominator <= 0) $denominator = 0.95;
            $gross = $liquid / $denominator;
        } else {
            // Formula: G = (Liquid + Fixed * (1 + IVA)) / (1 - P - f * (1 + IVA))
            // We use this to ensure that after all subtractions, we get exactly $liquid.
            $denominator = 1 - $p - ($f * (1 + $iva));
            if ($denominator <= 0) $denominator = 0.9; // Fallback safety

            $gross = ($liquid + ($fixed * (1 + $iva))) / $denominator;
        }

        $gross = (int)ceil($gross);

        return $this->calculateFeesFromGross($gross, $gatewayKey);
    }

    /**
     * Calculate Fees starting from a Gross Amount (what guest pays)
     */
    public function calculateFeesFromGross(int $gross, string $gatewayKey = 'flow'): array
    {
        $settings = Setting::all()->pluck('value', 'key');
        
        $p_pct = (float)($settings['platform_fee_percent'] ?? 0) / 100;
        
        if ($gatewayKey === 'transfer') {
            $f_pct = 0;
            $f_fixed = 0;
            $iva_pct = 0;
        } else {
            $f_pct = (float)($settings["{$gatewayKey}_fee_percent"] ?? 0) / 100;
            $f_fixed = (float)($settings["{$gatewayKey}_fee_fixed"] ?? 0);
            $iva_pct = (float)($settings['iva_percent'] ?? 0) / 100;
        }

        $platform_fee = (int)round($gross * $p_pct);
        $gateway_net_fee = ($gross * $f_pct) + $f_fixed;
        $gateway_iva = $gateway_net_fee * $iva_pct;
        $total_gateway_fee = (int)round($gateway_net_fee + $gateway_iva);

        $net_amount = $gross - $platform_fee - $total_gateway_fee;

        return [
            'gross' => $gross,
            'platform_fee' => $platform_fee,
            'gateway_fee' => $total_gateway_fee,
            'net_amount' => (int)$net_amount,
            'liquid' => (int)$net_amount,
            'iva_on_gateway' => (int)round($gateway_iva)
        ];
    }
}
