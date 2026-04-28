@extends('layouts.app')

@section('head')
    <!-- Add this meta tag to hint the browser to preload Amharic voices -->
    <meta name="google" content="notranslate">
    <html lang="am">

    <!-- Categories data must load before the JavaScript -->
    <script>
        window.categoriesData = @json($categories);
        window.appConfig = {
            speechDriver: "{{ $speechDriver ?? 'browser' }}"
        };
        console.log('Categories data loaded:', window.categoriesData);
    </script>

    <!-- Force voice download -->
    <script>
        window.addEventListener('load', function() {
            if ('speechSynthesis' in window) {
                speechSynthesis.getVoices();
            }
        });
    </script>
@endsection

@section('content')
<div class="practice-container">
    <div class="bg-animation">
        <div class="bg-blob bg-blob-1"></div>
        <div class="bg-blob bg-blob-2"></div>
    </div>

    <div class="practice-settings" id="practiceSettings">
        <h3>Practice Settings</h3>

        <!-- Mode Toggle -->
        <div style="display: flex; justify-content: center; margin-bottom: 0.75rem;">
            <div id="modeToggle" style="display: inline-flex; background: rgba(255,255,255,0.15); border-radius: 999px; padding: 4px; gap: 4px;">
                <button id="modeRandomBtn" onclick="setPracticeMode('random')"
                        style="padding: 6px 20px; border-radius: 999px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 600; background: white; color: #4f46e5; transition: all 0.2s;">
                    Random
                </button>
                <button id="modeConsecutiveBtn" onclick="setPracticeMode('consecutive')"
                        style="padding: 6px 20px; border-radius: 999px; border: none; cursor: pointer; font-size: 0.9rem; font-weight: 600; background: transparent; color: rgba(255,255,255,0.8); transition: all 0.2s;">
                    Consecutive
                </button>
            </div>
        </div>

        <div class="practice-modes">
            <button id="randomPracticeBtn" class="random-practice-btn">
                <i class="fas fa-random"></i>
                <span>Random Practice</span>
                <div class="btn-description">Practice with random words from all categories</div>
            </button>
            
            <button id="installAppBtn" class="install-app-btn" style="display: none;">
                <i class="fas fa-download"></i>
                <span>Install App</span>
                <div class="btn-description">Install on your device for a better experience</div>
            </button>
            <div class="mode-divider">
                <span>or</span>
            </div>
            <div class="category-practice">
                <h4>Category Practice</h4>

                <!-- Category pagination controls -->
                <div class="category-pagination">
                    <button class="pagination-btn" id="categoryPrevBtn" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <div class="page-indicator" id="categoryPageInfo">Page 1 of 1</div>
                    <button class="pagination-btn" id="categoryNextBtn" disabled>
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>

                <!-- Category buttons container -->
                <div class="category-buttons-container">
                    <div class="category-buttons" id="categoryButtons">
                        <!-- Categories will be populated here -->
                    </div>
                </div>

                <!-- Level selection -->
                <div class="level-buttons" id="levelButtons" style="display: none;">
                    <h5>Select Level</h5>

                    <!-- Level pagination controls -->
                    <div class="level-pagination">
                        <button class="pagination-btn" id="levelPrevBtn" disabled>
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <div class="page-indicator" id="levelPageInfo">Page 1 of 1</div>
                        <button class="pagination-btn" id="levelNextBtn" disabled>
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>

                    <!-- Level buttons container -->
                    <div class="level-buttons-container" id="levelButtonsContainer">
                        <!-- Levels will be added dynamically -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="practiceArea" class="practice-area" style="display: none;">
        <div class="practice-layout">
            <div class="word-gif-container" id="wordGifContainer">
                <div class="gif-wrapper">
                    <img id="wordGif" src="" alt="Word illustration" class="word-gif">
                </div>
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

            <div class="word-image-container" id="wordImageContainer">
                <div class="image-wrapper">
                    <img id="wordImage" src="" alt="Word illustration" class="word-image">
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
