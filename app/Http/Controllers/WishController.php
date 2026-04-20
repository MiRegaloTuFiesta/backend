<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Wish;

class WishController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            Wish::whereHas('event', function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            })->get()
        );
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'event_id' => 'required|exists:events,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'liquid_amount' => 'nullable|integer|min:0',
            'target_amount' => 'nullable|integer|min:0',
        ]);

        // Check if the event belongs to the user
        $event = $request->user()->events()->findOrFail($validated['event_id']);

        $wish = $event->wishes()->create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? '',
            'liquid_amount' => $validated['liquid_amount'] ?? 0,
            'target_amount' => $validated['target_amount'] ?? 0,
            'current_amount' => 0,
            'status' => 'pending',
        ]);

        return response()->json($wish, 201);
    }

    public function show(Request $request, string $id)
    {
        $wish = Wish::whereHas('event', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->with('contributions')->findOrFail($id);

        return response()->json($wish);
    }

    public function update(Request $request, string $id)
    {
        $wish = Wish::whereHas('event', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);

        if ($wish->status === 'completed') {
            return response()->json(['message' => 'No se puede editar un deseo completado.'], 422);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'liquid_amount' => 'nullable|integer|min:0',
            'target_amount' => 'nullable|integer|min:0',
        ]);
        
        $wish->update($validated);
        return response()->json($wish);
    }

    public function destroy(Request $request, string $id)
    {
        $wish = Wish::whereHas('event', function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        })->findOrFail($id);
        
        $wish->delete();
        return response()->json(null, 204);
    }
}
