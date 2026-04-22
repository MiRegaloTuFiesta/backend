<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReport;
use Illuminate\Http\Request;

class EventReportController extends Controller
{
    /**
     * Store a new report for an event (public — no auth required).
     */
    public function store(Request $request, string $uuid)
    {
        $event = Event::where('uuid', $uuid)->firstOrFail();

        $validated = $request->validate([
            'reporter_email' => 'required|email|max:255',
            'reason' => 'required|string|max:500',
        ]);

        // Idempotency: same email can't report same event twice
        $existing = EventReport::where('event_id', $event->id)
            ->where('reporter_email', $validated['reporter_email'])
            ->exists();

        if ($existing) {
            return response()->json(['message' => 'Ya enviaste un reporte para este evento.'], 422);
        }

        EventReport::create([
            'event_id' => $event->id,
            'reporter_email' => $validated['reporter_email'],
            'reason' => $validated['reason'],
            'status' => 'pending',
        ]);

        return response()->json(['message' => 'Reporte enviado. Nuestro equipo lo revisará pronto.'], 201);
    }

    /**
     * List all reports (admin only).
     */
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $reports = EventReport::with('event:id,uuid,name')
            ->latest()
            ->get();

        return response()->json($reports);
    }

    /**
     * Mark a report as reviewed (admin only).
     */
    public function review(Request $request, $id)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $report = EventReport::findOrFail($id);
        $report->update(['status' => 'reviewed']);

        return response()->json(['message' => 'Reporte marcado como revisado.']);
    }
}
