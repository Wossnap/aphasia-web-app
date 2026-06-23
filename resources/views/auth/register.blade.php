<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>Create Account - Amharic Practice</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-[#0F172A] text-slate-100 flex items-center justify-center relative overflow-hidden font-sans">
    <!-- Visual background enhancements -->
    <div class="absolute inset-0 bg-gradient-to-br from-[#0F172A] via-[#1E1B4B] to-[#0F172A] z-0"></div>
    <div class="absolute -top-40 -left-40 w-96 h-96 bg-violet-600/10 rounded-full blur-3xl z-0 pointer-events-none"></div>
    <div class="absolute -bottom-40 -right-40 w-96 h-96 bg-indigo-600/10 rounded-full blur-3xl z-0 pointer-events-none"></div>

    <div class="max-w-md w-full px-6 py-12 z-10 relative">
        <div class="backdrop-blur-xl bg-slate-950/40 border border-slate-800/80 rounded-2xl p-8 shadow-2xl shadow-black/40">
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-gradient-to-tr from-violet-600 to-indigo-600 text-white text-2xl font-bold shadow-lg shadow-violet-500/30 mb-4">
                    <i class="fas fa-microphone-alt"></i>
                </div>
                <h2 class="text-3xl font-extrabold tracking-tight bg-clip-text text-transparent bg-gradient-to-r from-white via-slate-200 to-violet-300">
                    Create Account
                </h2>
                <p class="mt-2 text-sm text-slate-400">
                    Sign up to start your Amharic practice
                </p>
            </div>

            @if(isset($errors) && $errors->any())
                <div class="mb-4 bg-rose-500/15 border border-rose-500/30 text-rose-400 px-4 py-3 rounded-xl text-sm flex flex-col gap-1">
                    @foreach ($errors->all() as $error)
                        <div class="flex items-center gap-2">
                            <i class="fas fa-exclamation-circle flex-shrink-0"></i>
                            <span>{{ $error }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            <form class="space-y-6" method="POST" action="{{ route('register.post') }}">
                @csrf
                <div class="space-y-4">
                    <div>
                        <label for="name" class="block text-sm font-semibold text-slate-300 mb-1.5">Name</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-user"></i>
                            </div>
                            <input id="name" name="name" type="text" autocomplete="name" required
                                   class="block w-full pl-10 pr-4 py-3 bg-slate-900/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all duration-200 text-sm"
                                   placeholder="Your name" value="{{ old('name') }}">
                        </div>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-slate-300 mb-1.5">Email Address</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <input id="email" name="email" type="email" autocomplete="email" required
                                   class="block w-full pl-10 pr-4 py-3 bg-slate-900/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all duration-200 text-sm"
                                   placeholder="you@example.com" value="{{ old('email') }}">
                        </div>
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-semibold text-slate-300 mb-1.5">Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input id="password" name="password" type="password" autocomplete="new-password" required
                                   class="block w-full pl-10 pr-4 py-3 bg-slate-900/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all duration-200 text-sm"
                                   placeholder="••••••••">
                        </div>
                    </div>

                    <div>
                        <label for="password_confirmation" class="block text-sm font-semibold text-slate-300 mb-1.5">Confirm Password</label>
                        <div class="relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-500">
                                <i class="fas fa-lock"></i>
                            </div>
                            <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required
                                   class="block w-full pl-10 pr-4 py-3 bg-slate-900/60 border border-slate-800 rounded-xl text-slate-100 placeholder-slate-500 focus:outline-none focus:border-violet-500 focus:ring-1 focus:ring-violet-500 transition-all duration-200 text-sm"
                                   placeholder="••••••••">
                        </div>
                    </div>
                </div>

                <div>
                    <button type="submit"
                            class="w-full flex justify-center py-3 px-4 rounded-xl text-sm font-bold text-white bg-gradient-to-r from-violet-600 to-indigo-600 hover:from-violet-500 hover:to-indigo-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-violet-500 transition-all duration-300 shadow-lg shadow-violet-600/20 active:scale-[0.98]">
                        Create Account
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-slate-400">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-violet-400 hover:text-violet-300">Sign in</a>
            </p>
        </div>
    </div>
</body>
</html>
