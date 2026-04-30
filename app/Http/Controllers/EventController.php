<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Event;

class EventController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $search = $request->query('search');
        $categoryId = $request->query('category_id');
        $cityId = $request->query('city_id');
        $requestedService = $request->query('requested_service');
        
        $query = Event::with(['wishes.contributions' => function($q) {
            $q->where('status', 'completed');
        }, 'user', 'assignedAdmin', 'city.region'])
            ->withSum(['manualPayments as manual_payments_sum_amount' => function($q) {
                $q->where('type', 'event');
            }], 'amount')
            ->withSum(['manualPayments as service_payments_sum_amount' => function($q) {
                $q->where('type', 'service');
            }], 'amount');

        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('uuid', 'like', "%{$search}%")
                  ->orWhereHas('user', function($qu) use ($search) {
                      $qu->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        if ($categoryId) {
            if ($categoryId === 'uncategorized') {
                $query->whereNull('category_id');
            } else {
                $query->where('category_id', $categoryId);
            }
        }

        if ($cityId) {
            $query->where('city_id', $cityId);
        }

        if ($requestedService && $requestedService !== 'all') {
            $query->where('requests_internal_service', $requestedService === 'yes');
        }

        if ($user->role === 'admin') {
            return response()->json($query->orderBy('created_at', 'desc')->get());
        }

        return response()->json($user->events()->with(['wishes.contributions' => function($q) {
            $q->where('status', 'completed');
        }, 'city.region', 'assignedAdmin', 'category'])->orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'date' => 'required|date',
            'category_id' => 'nullable|exists:categories,id',
            'city_id' => 'required|exists:cities,id',
            'address' => 'nullable|string|max:500',
            'is_location_public' => 'boolean',
            'creator_budget' => 'nullable|integer|min:0',
            'requests_internal_service' => 'boolean',
        ]);

        if (!$request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Debes verificar tu correo electrónico antes de crear un evento.'
            ], 403);
        }

        $event = Event::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'date' => $validated['date'],
            'category_id' => $validated['category_id'] ?? null,
            'city_id' => $validated['city_id'],
            'address' => $validated['address'] ?? null,
            'is_location_public' => $validated['is_location_public'] ?? true,
            'creator_budget' => $validated['creator_budget'] ?? null,
            'requests_internal_service' => $validated['requests_internal_service'] ?? false,
            'total_price' => $validated['creator_budget'] ?? 0,
            'collected_amount' => 0,
            'overflow_balance' => 0,
            'status' => 'approved',
        ]);

        return response()->json($event, 201);
    }

    public function show(string $uuid)
    {
        $event = Event::where('uuid', $uuid)
            ->with(['wishes' => function($query) {
                $query->with(['contributions' => function($q) {
                    $q->where('status', 'completed');
                }]);
            }, 'city.region', 'user'])
            ->withSum(['manualPayments as manual_payments_sum_amount' => function($q) {
                $q->where('type', 'event');
            }], 'amount')
            ->withSum(['manualPayments as service_payments_sum_amount' => function($q) {
                $q->where('type', 'service');
            }], 'amount')
            ->firstOrFail();
        
        return response()->json($event);
    }

    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $event = ($user->role === 'admin') 
            ? Event::findOrFail($id)
            : $user->events()->findOrFail($id);

        if ($user->role === 'admin') {
            // Admins can update any field
            $event->update($request->all());
        } else {
            // Creators can only update their own safe metadata fields
            $validated = $request->validate([
                'name' => 'sometimes|string|max:255',
                'date' => 'sometimes|date',
                'category_id' => 'nullable|exists:categories,id',
                'city_id' => 'nullable|exists:cities,id',
                'address' => 'nullable|string|max:500',
                'is_location_public' => 'sometimes|boolean',
                'creator_budget' => 'nullable|integer|min:0',
            ]);
            $event->update($validated);
        }

        return response()->json($event);
    }

    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $event = ($user->role === 'admin') 
            ? Event::findOrFail($id)
            : $user->events()->findOrFail($id);

        // Creators can only delete pending events
        if ($user->role !== 'admin' && $event->status !== 'pending') {
            return response()->json(['message' => 'Solo puedes eliminar eventos en estado pendiente.'], 403);
        }
            
        $event->delete();
        return response()->json(null, 204);
    }
}
