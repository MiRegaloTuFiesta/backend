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
                'pending_reports' => \App\Models\EventReport::where('status', 'pending')->count(),
            ],
            'upcoming_alerts' => $upcomingEvents,
            'recent_contributions' => $recentContributions,
            'chart_data' => $chartData,
            'recent_users' => User::latest()->limit(5)->get(['id', 'name', 'email', 'created_at']),
            'recent_events' => Event::with('user:id,name')->latest()->limit(5)->get(['id', 'name', 'user_id', 'created_at']),
        ]);
    }

    public function users(Request $request)
    {
        $search = $request->query('search');
        $role = $request->query('role');
        $query = User::with(['bank', 'accountType']);

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
            'phone' => 'sometimes|string|max:20|nullable',
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
        $categoryId = $request->query('category_id');
        $cityId = $request->query('city_id');
        $requestedService = $request->query('requested_service');

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
            if ($categoryId) {
                $cQuery->whereHas('wish.event', function($q) use ($categoryId) {
                    if ($categoryId === 'uncategorized') $q->whereNull('category_id');
                    else $q->where('category_id', $categoryId);
                });
            }
            if ($cityId) {
                $cQuery->whereHas('wish.event', function($q) use ($cityId) {
                    $q->where('city_id', $cityId);
                });
            }
            if ($requestedService && $requestedService !== 'all') {
                $cQuery->whereHas('wish.event', function($q) use ($requestedService) {
                    $q->where('requests_internal_service', $requestedService === 'yes');
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
                    'is_deposited' => (bool)$c->is_deposited,
                    'deposited_at' => $c->deposited_at,
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
            if ($categoryId) {
                $mQuery->whereHas('event', function($q) use ($categoryId) {
                    if ($categoryId === 'uncategorized') $q->whereNull('category_id');
                    else $q->where('category_id', $categoryId);
                });
            }
            if ($cityId) {
                $mQuery->whereHas('event', function($q) use ($cityId) {
                    $q->where('city_id', $cityId);
                });
            }
            if ($requestedService && $requestedService !== 'all') {
                $mQuery->whereHas('event', function($q) use ($requestedService) {
                    $q->where('requests_internal_service', $requestedService === 'yes');
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
                    'is_deposited' => (bool)$m->is_deposited,
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
            'total_net' => (int)$countablePayments->where('is_deposited', false)->sum('net_to_user'),
            'total_deposited' => (int)$countablePayments->where('is_deposited', true)->sum('net_to_user'),
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
        $keys = ['enable_flow', 'enable_mp', 'enable_transfer', 'transfer_bank_details', 'payout_days', 'enable_manual_payments', 'enable_internal_service'];
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

    public function payouts()
    {
        $payoutDays = (int)Setting::where('key', 'payout_days')->first()?->value ?? 3;
        
        // Users with pending deposits (contributions or manual payments not yet deposited)
        $users = User::where('role', 'creator')
            ->where(function($query) {
                $query->whereHas('events.wishes.contributions', function($q) {
                    $q->where('status', 'completed')->where('is_deposited', false);
                })->orWhereHas('events.manualPayments', function($q) {
                    $q->where('type', 'event')->where('is_deposited', false);
                });
            })
            ->with([
                'bank', 
                'accountType', 
                'events.wishes.contributions',
                'events.manualPayments'
            ])
            ->get();

        $data = $users->map(function($user) use ($payoutDays) {
            $pendingContributions = $user->events->flatMap(function($event) {
                return $event->wishes->flatMap(function($wish) {
                    return $wish->contributions->where('status', 'completed')->where('is_deposited', false);
                });
            });

            $pendingManual = $user->events->flatMap(function($event) {
                return $event->manualPayments->where('type', 'event')->where('is_deposited', false);
            });

            $allPendingEntries = $pendingContributions->concat($pendingManual);
            $oldestEntry = $allPendingEntries->sortBy('created_at')->first();
            $daysPassed = $oldestEntry ? $oldestEntry->created_at->diffInDays(now()) : 0;
            $daysRemaining = $payoutDays - $daysPassed;

            $depositedContributions = $user->events->flatMap(function($event) {
                return $event->wishes->flatMap(function($wish) {
                    return $wish->contributions->where('status', 'completed')->where('is_deposited', true);
                });
            })->sum('net_to_user');

            $depositedManual = $user->events->flatMap(function($event) {
                return $event->manualPayments->where('type', 'event')->where('is_deposited', true);
            })->sum('amount');

            $depositedBalance = $depositedContributions + $depositedManual;

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'bank_details' => $user->bank ? [
                    'bank_name' => $user->bank->name,
                    'account_type' => $user->accountType?->name,
                    'account_number' => $user->account_number,
                    'bank_rut' => $user->bank_rut
                ] : null,
                'total_pending' => (int)($pendingContributions->sum('net_to_user') + $pendingManual->sum('amount')),
                'total_deposited' => (int)$depositedBalance,
                'oldest_contribution_at' => $oldestEntry?->created_at,
                'days_remaining' => (int)$daysRemaining,
                'is_delayed' => $daysRemaining < 0,
                'contribution_ids' => $pendingContributions->pluck('id')->all(),
                'manual_payment_ids' => $pendingManual->pluck('id')->all(),
                'details' => $pendingContributions->map(function($c) {
                    return [
                        'id' => 'c_' . $c->id,
                        'wish_name' => $c->wish?->name,
                        'event_name' => $c->wish?->event?->name,
                        'donor_name' => $c->donor_name,
                        'amount' => (int)$c->net_to_user,
                        'date' => $c->created_at->format('d/m/Y'),
                        'type' => 'contribution'
                    ];
                })->concat($pendingManual->map(function($m) {
                    return [
                        'id' => 'm_' . $m->id,
                        'wish_name' => 'Abono Manual',
                        'event_name' => $m->event?->name,
                        'donor_name' => $m->description,
                        'amount' => (int)$m->amount,
                        'date' => $m->created_at->format('d/m/Y'),
                        'type' => 'manual'
                    ];
                }))
            ];
        });

        return response()->json($data);
    }

    public function payoutHistory()
    {
        $contributions = Contribution::where('is_deposited', true)
            ->where('status', 'completed')
            ->with(['wish.event.user.bank', 'wish.event.user.accountType'])
            ->get();

        $manualPayments = ManualPayment::where('is_deposited', true)
            ->where('type', 'event')
            ->with(['event.user.bank', 'event.user.accountType'])
            ->get();

        $all = $contributions->concat($manualPayments)->sortByDesc('deposited_at');

        $grouped = $all->groupBy(function($item) {
             $userId = ($item instanceof Contribution) ? $item->wish->event->user_id : $item->event->user_id;
             return $userId . '_' . $item->deposited_at->format('Y-m-d');
        });

        $data = $grouped->map(function($items) {
            $first = $items->first();
            $user = ($first instanceof Contribution) ? $first->wish->event->user : $first->event->user;

            return [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'user_email' => $user->email,
                'deposited_at' => $first->deposited_at->format('d/m/Y'),
                'deposited_at_raw' => $first->deposited_at,
                'bank_details' => $user->bank ? [
                    'bank_name' => $user->bank->name,
                    'account_type' => $user->accountType?->name,
                    'account_number' => $user->account_number,
                    'bank_rut' => $user->bank_rut
                ] : null,
                'total_deposited' => (int)$items->sum(function($item) {
                    return ($item instanceof Contribution) ? $item->net_to_user : $item->amount;
                }),
                'details' => $items->map(function($item) {
                     if ($item instanceof Contribution) {
                         return [
                            'id' => 'c_' . $item->id,
                            'wish_name' => $item->wish?->name,
                            'event_name' => $item->wish?->event?->name,
                            'donor_name' => $item->donor_name,
                            'amount' => (int)$item->net_to_user,
                            'date' => $item->created_at->format('d/m/Y')
                        ];
                     } else {
                         return [
                            'id' => 'm_' . $item->id,
                            'wish_name' => 'Abono Manual',
                            'event_name' => $item->event?->name,
                            'donor_name' => $item->description,
                            'amount' => (int)$item->amount,
                            'date' => $item->created_at->format('d/m/Y')
                        ];
                     }
                })
            ];
        })->values();

        return response()->json($data);
    }

    public function completePayout(Request $request, $userId)
    {
        $contributionIds = $request->input('contribution_ids', []);
        $manualPaymentIds = $request->input('manual_payment_ids', []);
        $now = now();
        
        if (empty($contributionIds) && empty($manualPaymentIds)) {
            return response()->json(['message' => 'No se seleccionaron pagos'], 422);
        }

        if (!empty($contributionIds)) {
            Contribution::whereIn('id', $contributionIds)
                ->update([
                    'is_deposited' => true,
                    'deposited_at' => $now
                ]);
        }

        if (!empty($manualPaymentIds)) {
            ManualPayment::whereIn('id', $manualPaymentIds)
                ->update([
                    'is_deposited' => true,
                    'deposited_at' => $now
                ]);
        }

        return response()->json(['message' => 'Depósito marcado como completado']);
    }
}
