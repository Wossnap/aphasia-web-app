<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amharic Letter Game</title>
    {{-- <link rel="stylesheet" href="{{ asset('css/app.css') }}"> --}}
    <link href="{{ asset('css/amharic-practice.css') }}" rel="stylesheet">

    <!-- Add translations for JavaScript -->
    <script>
        window.translations = {
            next_word: "{{ __('app.next_word') }}",
            excellent: "{{ __('app.excellent') }}",
            you_said: "{{ __('app.you_said') }}",
            try_again: "{{ __('app.try_again') }}"
        };

        // Handle language switch reload
        @if(session('reload'))
            window.location.reload(true);
        @endif
    </script>
</head>
<body>
    <div class="min-h-screen bg-gray-100">
        <!-- Language Switcher -->
        <div class="language-switcher">
            <div class="relative" x-data="{ open: false }">
                <button
                    @click="open = !open"
                    class="language-button"
                >
                    <span>{{ __('app.language') }}</span>
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div
                    x-show="open"
                    @click.away="open = false"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 transform scale-95"
                    x-transition:enter-end="opacity-100 transform scale-100"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 transform scale-100"
                    x-transition:leave-end="opacity-0 transform scale-95"
                    class="language-menu"
                >
                    <a href="{{ route('language.switch', 'en') }}"
                       class="{{ app()->getLocale() == 'en' ? 'active' : '' }}"
                       onclick="window.location.reload()"
                    >
                        {{ __('app.english') }}
                    </a>
                    <a href="{{ route('language.switch', 'am') }}"
                       class="{{ app()->getLocale() == 'am' ? 'active' : '' }}"
                       onclick="window.location.reload()"
                    >
                        {{ __('app.amharic') }}
                    </a>
                </div>
            </div>
        </div>

        <!-- Existing content -->
        @yield('content')
    </div>

    <!-- Make sure Alpine.js is included -->
    <script src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</body>
<script src="{{ asset('js/amharic-practice.js') }}"></script>

</html>
