<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class UserAuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('practice.amharic');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // "once login don't let it expire... only with an option to logout":
        // Always pass true to the remember parameter to set a persistent cookie.
        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();

            Log::info('User authenticated successfully', [
                'user_id' => Auth::id(),
                'email' => Auth::user()->email,
            ]);

            return redirect()->intended(route('practice.amharic'));
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    public function showRegister()
    {
        if (Auth::check()) {
            return redirect()->route('practice.amharic');
        }

        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // Persistent login (no expiry) — matches the login flow.
        Auth::login($user, true);
        $request->session()->regenerate();

        Log::info('User registered successfully', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return redirect()->intended(route('practice.amharic'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Logged out successfully.');
    }
}
