<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/ForgotPassword', [
            'status' => session('status'),
            'resetLink' => session('reset_link'),
        ]);
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = Password::broker()->getUser(['email' => $request->input('email')]);

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => [trans(Password::INVALID_USER)],
            ]);
        }

        $token = Password::broker()->createToken($user);
        $user->sendPasswordResetNotification($token);

        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        if ($request->wantsJson()) {
            return response()->json([
                'status' => trans(Password::RESET_LINK_SENT),
                'reset_url' => $resetUrl,
            ]);
        }

        return back()->with('status', trans(Password::RESET_LINK_SENT))
            ->with('reset_link', $resetUrl);
    }
}
