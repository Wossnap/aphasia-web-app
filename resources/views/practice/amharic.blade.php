@extends('layouts.app')


@section('content')
<div class="practice-container">
    <div class="bg-animation">
        <div class="bg-blob bg-blob-1"></div>
        <div class="bg-blob bg-blob-2"></div>
    </div>

    <div class="practice-settings" id="practiceSettings">
        <h3>Practice Settings</h3>
        <div class="practice-modes">
            <button id="randomPracticeBtn" class="random-practice-btn">
                <i class="fas fa-random"></i>
                <span>Random Practice</span>
                <div class="btn-description">Practice with random words from all categories</div>
            </button>
            <div class="mode-divider">
                <span>or</span>
            </div>
            <div class="category-practice">
                <h4>Category Practice</h4>
                <div class="category-buttons">
                    @foreach($categories as $category)
                        <button class="category-btn" data-category="{{ $category->id }}">
                            {{ $category->name }}
                        </button>
                    @endforeach
                </div>
                <div class="level-buttons" style="display: none;">
                    <h5>Select Level</h5>
                    <div class="level-buttons-container">
                        <!-- Levels will be added dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="practiceArea" class="practice-area" style="display: none;">
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
                    <p class="speech-status">Listening...</p>
                    <p class="speech-result">Spoken word: <span id="spokenWord"></span></p>
                </div>

                <div class="button-container">
                    <button id="nextWordBtn" class="next-word-btn">
                        <span>Next Word</span>
                    </button>
                    <button id="stopBtn" class="stop-btn">
                        <i class="fas fa-stop-circle"></i>
                        <span>Stop</span>
                    </button>
                </div>

                <div class="mobile-controls">
                    <button id="requestPermissionBtn" class="permission-btn">
                        <i class="fas fa-microphone"></i> Allow Microphone
                    </button>
                </div>
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
