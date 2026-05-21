<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\SupportMessage;
use Illuminate\Http\Request;

class SupportChatController extends Controller
{
    public function index(Request $request, $userId = null)
    {
        $currentUser = $request->user();

        if ($userId === 'undefined' || $userId === 'null') {
            $userId = null;
        }

        // Si es admin y pide explícitamente ver la lista de hilos de chat activos (vía query 'threads')
        if ($currentUser->role === 'admin' && $request->query('threads') === 'true') {
            $threads = User::where('role', 'creator')
                ->whereHas('supportMessages')
                ->get()
                ->map(function ($u) {
                    $lastMsg = SupportMessage::where('user_id', $u->id)->latest()->first();
                    $unreadCount = SupportMessage::where('user_id', $u->id)
                        ->where('sender_id', '!=', auth()->id())
                        ->where('is_read', false)
                        ->count();

                    $u->last_message = $lastMsg ? $lastMsg->message : null;
                    $u->last_message_at = $lastMsg ? $lastMsg->created_at : null;
                    $u->unread_count = $unreadCount;
                    return $u;
                })
                ->sortByDesc('last_message_at')
                ->values();

            return response()->json($threads);
        }

        // Si no se pasó un userId, se asume la conversación propia del usuario autenticado (sea creador o admin)
        if (!$userId) {
            $userId = $currentUser->id;
        }

        // Devolver el historial de chat para el usuario especificado
        $messages = SupportMessage::where('user_id', $userId)
            ->with(['sender:id,name,role', 'event:id,name,uuid'])
            ->oldest()
            ->get();

        return response()->json($messages);
    }

    public function store(Request $request, $userId = null)
    {
        $currentUser = $request->user();

        if ($userId === 'undefined' || $userId === 'null') {
            $userId = null;
        }

        // Si no se pasó un userId, por defecto es el del propio usuario autenticado (sea creador o admin)
        if (!$userId) {
            $userId = $currentUser->id;
        }

        if ($request->has('event_id') && ($request->input('event_id') === 'undefined' || $request->input('event_id') === 'null')) {
            $request->merge(['event_id' => null]);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:5000',
            'event_id' => 'nullable|integer|exists:events,id',
        ]);

        $message = SupportMessage::create([
            'user_id' => $userId,
            'sender_id' => $currentUser->id,
            'event_id' => $validated['event_id'] ?? null,
            'message' => $validated['message'],
            'is_read' => false,
        ]);

        $message->load(['sender:id,name,role', 'event:id,name,uuid']);

        return response()->json($message, 201);
    }

    public function markAsRead(Request $request, $userId = null)
    {
        $currentUser = $request->user();

        if ($userId === 'undefined' || $userId === 'null') {
            $userId = null;
        }

        // Si no se pasó un userId, por defecto es el del propio usuario autenticado (sea creador o admin)
        if (!$userId) {
            $userId = $currentUser->id;
        }

        SupportMessage::where('user_id', $userId)
            ->where('sender_id', '!=', $currentUser->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }
}
