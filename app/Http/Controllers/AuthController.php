<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Password;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:20',
            'bank_id' => 'nullable|exists:banks,id',
            'account_type_id' => 'nullable|exists:account_types,id',
            'account_number' => 'nullable|string|max:50',
        ]);

        // Conditional validation for bank details
        if ($request->bank_id || $request->account_type_id || $request->account_number) {
            $request->validate([
                'bank_id' => 'required',
                'account_type_id' => 'required',
                'account_number' => 'required',
            ]);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'creator',
            'phone' => $request->phone,
            'bank_id' => $request->bank_id,
            'account_type_id' => $request->account_type_id,
            'account_number' => $request->account_number,
        ]);

        event(new Registered($user));

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => false,
            ],
            'token' => $user->createToken('auth_token')->plainTextToken
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales proporcionadas son incorrectas.'],
            ]);
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'email_verified' => !is_null($user->email_verified_at),
            ],
            'token' => $user->createToken('auth_token')->plainTextToken
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Handle forgot password request.
     */
    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['message' => 'Se ha enviado un enlace de recuperación a tu correo.'])
            : response()->json(['message' => 'No pudimos enviar el enlace. Intenta más tarde.'], 500);
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => 'Tu contraseña ha sido restablecida con éxito. Ahora puedes iniciar sesión.'])
            : response()->json(['message' => 'El enlace de recuperación es inválido o ha expirado.'], 400);
    }

    /**
     * Handle email verification callback.
     */
    public function verifyEmail(Request $request, $id, $hash)
    {
        $user = User::findOrFail($id);

        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return redirect()->to(env('FRONTEND_URL') . '/login?verified=0');
        }

        if ($user->hasVerifiedEmail()) {
            return redirect()->to(env('FRONTEND_URL') . '/dashboard?verified=1');
        }

        if ($user->markEmailAsVerified()) {
            event(new Registered($user));
        }

        return redirect()->to(env('FRONTEND_URL') . '/dashboard?verified=1');
    }

    /**
     * Resend verification email.
     */
    public function resendVerification(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json(['message' => 'Tu correo ya ha sido verificado.']);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json(['message' => 'Se ha enviado un nuevo enlace de verificación a tu correo.']);
    }
}
