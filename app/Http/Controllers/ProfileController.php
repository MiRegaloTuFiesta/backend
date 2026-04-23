<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    /**
     * Update user profile data including bank details
     */
    public function update(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'bank_id' => 'nullable|exists:banks,id',
            'account_type_id' => 'nullable|exists:account_types,id',
            'account_number' => 'nullable|string|max:50',
            'bank_rut' => ['nullable', 'string', new \App\Rules\RutValid],
        ]);

        // Email changed logic
        if (isset($validated['email']) && $validated['email'] !== $user->email) {
            $user->email_verified_at = null;
            $user->email = $validated['email'];
            $user->sendEmailVerificationNotification();
        }

        // Conditional validation: if any bank detail is provided, all must be provided
        if ($request->bank_id || $request->account_type_id || $request->account_number || $request->bank_rut) {
            $request->validate([
                'bank_id' => 'required',
                'account_type_id' => 'required',
                'account_number' => 'required',
                'bank_rut' => ['required', new \App\Rules\RutValid],
            ]);
        }

        $user->fill($validated);
        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado con éxito',
            'user' => $user->load(['bank', 'accountType'])
        ]);
    }

    /**
     * Update user password following best practices
     */
    public function updatePassword(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Contraseña actualizada con éxito']);
    }

    /**
     * Get financial summary for the creator
     */
    public function payoutSummary(Request $request)
    {
        $user = $request->user();

        // Pending: completed contributions but NOT deposited
        $pending = \App\Models\Contribution::where('status', 'completed')
            ->where('is_deposited', false)
            ->whereHas('wish.event', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->sum('net_to_user');

        // Completed: completed and deposited
        $completed = \App\Models\Contribution::where('status', 'completed')
            ->where('is_deposited', true)
            ->whereHas('wish.event', function($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->sum('net_to_user');

        return response()->json([
            'pending_balance' => (int)$pending,
            'completed_balance' => (int)$completed
        ]);
    }
}
