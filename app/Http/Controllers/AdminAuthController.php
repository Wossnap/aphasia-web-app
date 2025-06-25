<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use App\Models\User;

class AdminAuthController extends Controller
{
        public function showLogin()
    {
        if (Auth::check() && Auth::user()->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Debug logging
        Log::info('Login attempt', [
            'email' => $request->email,
            'password_length' => strlen($request->password)
        ]);

        $credentials = $request->only('email', 'password');

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $user = Auth::user();

            Log::info('Authentication successful', [
                'user_id' => $user->id,
                'email' => $user->email,
                'is_admin' => $user->is_admin
            ]);

            if ($user->is_admin) {
                Log::info('Admin access granted, redirecting to dashboard');
                return redirect()->intended(route('admin.dashboard'));
            } else {
                Log::info('User is not admin, logging out');
                Auth::logout();
                return back()->withErrors(['email' => 'Access denied. Admin privileges required.']);
            }
        }

        Log::info('Authentication failed');
        return back()->withErrors(['email' => 'Invalid credentials.'])->withInput($request->except('password'));
    }

    public function logout()
    {
        Auth::logout();
        return redirect()->route('admin.login')->with('success', 'Logged out successfully.');
    }
}
