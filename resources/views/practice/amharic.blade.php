@extends('layouts.app')


@section('content')
<div class="practice-container">
    <div class="bg-animation">
        <div class="bg-blob bg-blob-1"></div>
        <div class="bg-blob bg-blob-2"></div>
    </div>

    <div class="practice-card">
        <div class="word-header">
            <div class="grid-pattern"></div>
            <div class="gradient-overlay"></div>
            <h2 class="amharic-word" id="amharicWord"></h2>
        </div>

        <div class="p-8 relative z-10">
            <div id="speechFeedback" class="speech-feedback">
                <div class="listening-indicator">
                    <div class="circle-ripple"></div>
                    <div class="circle-core"></div>
                </div>
                <p class="speech-status">{{ __('app.listening') }}</p>
                <p class="speech-result">{{ __('app.spoken_word') }} <span id="spokenWord"></span></p>
            </div>

            <div class="button-container">
                <button id="actionBtn" class="start-btn">
                    <span>{{ __('app.start_practice') }}</span>
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('head')
    <!-- Add this meta tag to hint the browser to preload Amharic voices -->
    <meta name="google" content="notranslate">
    <html lang="am">
    <!-- Force voice download -->
    <script>
        window.addEventListener('load', function() {
            if ('speechSynthesis' in window) {
                speechSynthesis.getVoices();
            }
        });
    </script>
@endsection
