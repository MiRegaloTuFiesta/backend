<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\Contribution;
use App\Models\Setting;
use App\Models\ManualPayment;
use App\Notifications\ManualPaymentReceivedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function stats()
    {
        $totalCollected = Contribution::where('status', 'completed')->sum('amount');
        $totalPlatformProfit = Contribution::where('status', 'completed')->sum('platform_fee');
        $totalEvents = Event::count();
        $pendingEvents = Event::where('status', 'pending')->count();
        $totalUsers = User::count();

        // Alertas de transferencia: eventos a 30 y 15 días (o menos)
        // Filtramos eventos aprobados que no han terminado y cuya fecha está próxima.
        $upcomingEvents = Event::where('status', 'approved')
            ->whereBetween('date', [now()->startOfDay(), now()->addDays(30)->endOfDay()])
            ->orderBy('date', 'asc')
            ->get()
            ->map(function ($event) {
                $daysLeft = now()->startOfDay()->diffInDays(Carbon::parse($event->date), false);
                $event->days_left = $daysLeft;
                $event->alert_type = $daysLeft <= 15 ? 'critical' : 'warning';
                return $event;
            });

        // Recent successful contributions
        $recentContributions = Contribution::where('status', 'completed')
            ->with(['wish.event'])
            ->latest()
            ->limit(5)
            ->get();

        // Chart data: Contributions per day (last 7 days)
        $rawChartData = Contribution::where('status', 'completed')
            ->where('created_at', '>=', now()->subDays(6)->startOfDay())
            ->select(DB::raw('date(created_at) as date_label'), DB::raw('sum(amount) as total'))
            ->groupBy('date_label')
            ->get()
            ->pluck('total', 'date_label');

        $chartData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $chartData[] = [
                'date' => $date,
                'total' => (int)($rawChartData[$date] ?? 0)
            ];
        }

        return response()->json([
            'summary' => [
                'total_collected' => (int)$totalCollected,
                'total_platform_profit' => (int)$totalPlatformProfit,
                'total_events' => $totalEvents,
                'pending_events' => $pendingEvents,
                'total_users' => $totalUsers,
            ],
            'upcoming_alerts' => $upcomingEvents,
            'recent_contributions' => $recentContributions,
            'chart_data' => $chartData,
        ]);
    }

    public function users(Request $request)
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $query = User::query();

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($role) {
            $query->where('role', $role);
        }

        return response()->json($query->latest()->get());
    }

    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $id,
            'role' => 'sometimes|in:admin,creator',
        ]);

        $user->update($validated);
        return response()->json($user);
    }

    public function destroyUser(Request $request, $id)
    {
        $user = User::findOrFail($id);
        
        // Prevent self-deletion if needed (optional but recommended)
        if ($user->id === (int) $request->user()?->id) {
            return response()->json(['message' => 'No puedes eliminarte a ti mismo'], 403);
        }

        $user->delete();
        return response()->json(null, 204);
    }

    public function payments(Request $request)
    {
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $userId = $request->query('user_id');
        $paymentType = $request->query('payment_type', 'all'); // all, automatic, manual_event, manual_service

        $contributionsItems = collect();
        $manualPaymentsItems = collect();

        // 1. Fetch Contributions (Automatic)
        if (in_array($paymentType, ['all', 'automatic'])) {
            $cQuery = Contribution::with(['wish.event.user'])
                ->where('status', 'completed');
            
            if ($startDate) $cQuery->whereDate('created_at', '>=', $startDate);
            if ($endDate) $cQuery->whereDate('created_at', '<=', $endDate);
            if ($userId) {
                $cQuery->whereHas('wish.event', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }
            
            $contributionsItems = $cQuery->get()->map(function($c) {
                return [
                    'id' => 'c_' . $c->id,
                    'donor_name' => $c->donor_name,
                    'event_name' => $c->wish?->event?->name ?? 'N/A',
                    'amount' => (int)$c->amount,
                    'platform_fee' => (int)$c->platform_fee,
                    'gateway_fee' => (int)$c->gateway_fee,
                    'net_to_user' => (int)$c->net_to_user,
                    'payment_method' => $c->payment_method,
                    'created_at' => $c->created_at,
                    'type' => 'automatic',
                ];
            });
        }

        // 2. Fetch Manual Payments
        if (in_array($paymentType, ['all', 'manual_event', 'manual_service', 'manual'])) {
            $mQuery = ManualPayment::with(['event.user']);
            
            if ($startDate) $mQuery->whereDate('created_at', '>=', $startDate);
            if ($endDate) $mQuery->whereDate('created_at', '<=', $endDate);
            if ($userId) {
                $mQuery->whereHas('event', function($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            }

            if ($paymentType === 'manual_event') $mQuery->where('type', 'event');
            if ($paymentType === 'manual_service') $mQuery->where('type', 'service');

            $manualPaymentsItems = $mQuery->get()->map(function($m) {
                return [
                    'id' => 'm_' . $m->id,
                    'donor_name' => $m->description, 
                    'event_name' => $m->event?->name ?? 'N/A',
                    'amount' => (int)$m->amount,
                    'platform_fee' => 0,
                    'gateway_fee' => 0,
                    'net_to_user' => (int)$m->amount,
                    'payment_method' => 'Manual',
                    'created_at' => $m->created_at,
                    'type' => 'manual_' . $m->type, 
                ];
            });
        }

        // Unify
        $allPayments = $contributionsItems->concat($manualPaymentsItems)->sortByDesc('created_at')->values();

        // Stats - Only count automatic and manual_event for metrics
        $countablePayments = $allPayments->filter(function($p) {
            return $p['type'] === 'automatic' || $p['type'] === 'manual_event';
        });

        $stats = [
            'total_gross' => (int)$countablePayments->sum('amount'),
            'total_platform_fee' => (int)$countablePayments->sum('platform_fee'),
            'total_gateway_fee' => (int)$countablePayments->sum('gateway_fee'),
            'total_net' => (int)$countablePayments->sum('net_to_user'),
            'count' => $allPayments->count(),
        ];

        // Chart Data
        $chartData = $countablePayments->groupBy(function($item) {
            return Carbon::parse($item['created_at'])->format('Y-m-d');
        })->map(function($group) {
            return [
                'total' => (int)$group->sum('amount'),
                'net' => (int)$group->sum('net_to_user'),
            ];
        })->toArray();

        return response()->json([
            'data' => $allPayments,
            'stats' => $stats,
            'chart_data' => $chartData
        ]);
    }

    public function getSettings()
    {
        return response()->json(Setting::all()->pluck('value', 'key'));
    }

    public function updateSettings(Request $request)
    {
        $settings = $request->all();
        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => (string)$value]);
        }
        return response()->json(['message' => 'Configuraciones actualizadas con éxito']);
    }

    public function getEventManualPayments($eventId)
    {
        $payments = ManualPayment::where('event_id', $eventId)
            ->latest()
            ->get();
            
        return response()->json($payments);
    }

    public function storeEventManualPayment(Request $request, $eventId)
    {
        $event = Event::findOrFail($eventId);
        
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'description' => 'required|string|max:255',
            'type' => 'sometimes|in:event,service',
        ]);

        $payment = ManualPayment::create([
            'event_id' => $event->id,
            'amount' => $validated['amount'],
            'description' => $validated['description'],
            'type' => $validated['type'] ?? 'event',
        ]);

        // Notify the creator
        if ($event->user) {
            $event->user->notify(new ManualPaymentReceivedNotification($payment));
        }

        return response()->json($payment, 201);
    }

    public function getPublicSettings()
    {
        $keys = ['enable_flow', 'enable_mp', 'enable_transfer', 'transfer_bank_details'];
        return response()->json(Setting::whereIn('key', $keys)->get()->pluck('value', 'key'));
    }

    public function getPendingTransfers($eventId)
    {
        return response()->json(
            Contribution::where('status', 'pending')
                ->where('payment_method', 'transfer')
                ->whereHas('wish', function($q) use ($eventId) {
                    $q->where('event_id', $eventId);
                })
                ->with('wish')
                ->get()
        );
    }

    public function approveTransfer(Request $request, $id)
    {
        $contribution = Contribution::findOrFail($id);
        
        if ($contribution->status === 'completed') {
            return response()->json(['message' => 'Transferencia ya aprobada'], 422);
        }

        $contribution->update([
            'status' => 'completed',
            'payment_id' => 'TRF-' . uniqid(),
            'gateway_fee' => 0,
            'platform_fee' => 0,
            'net_to_user' => $contribution->amount,
            'payment_method' => 'Transferencia'
        ]);

        $wish = $contribution->wish;
        $wish->current_amount += $contribution->amount;
        if ($wish->target_amount > 0 && $wish->current_amount >= $wish->target_amount) {
            $wish->status = 'completed';
        }
        $wish->save();

        $event = $wish->event;
        $event->collected_amount += $contribution->amount;
        $event->save();

        return response()->json(['message' => 'Transferencia aprobada con éxito']);
    }
}
