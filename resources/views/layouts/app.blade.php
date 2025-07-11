<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Amharic Letter Game</title>
    {{-- <link rel="stylesheet" href="{{ asset('css/app.css') }}"> --}}
    <link href="{{ asset('css/amharic-practice.css') }}" rel="stylesheet">

    <!-- Add Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

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

    @yield('head')
</head>
<body>
    <div class="min-h-screen bg-gray-100">


        <!-- Existing content -->
        @yield('content')
    </div>


    <!-- Load JavaScript after all content is rendered -->
    <script src="{{ asset('js/amharic-practice.js') }}"></script>
</body>
</html>
