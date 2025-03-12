class AmharicPractice {
    constructor() {
        this.setupMobileDebugger();
        this.currentWord = null;
        this.recognition = null;
        this.speechSynthesis = window.speechSynthesis;
        this.voices = [];
        this.voicesLoaded = false;
        this.isListening = false;
        this.isStarted = false;
        this.speakingQueue = [];
        this.isRecognitionActive = false;
        this.isSpeaking = false;
        this.mobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        this.pageVisible = true;
        this.manualListeningMode = true; // Enable manual mode for all devices, not just mobile
        this.mobileRecognitionDelay = 1000; // Added for mobile recognition delay
        this.currentCategory = null;
        this.currentLevel = null;

        // Initialize voices first
        this.initializeVoices().then(() => {
            this.initializeSpeechRecognition();
            this.initializeEventListeners();
            this.setupVisibilityHandling();
            this.initializeSettingsControls();
            this.updateButtonText();
        });
    }

    setupMobileDebugger() {
        if (!/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
            return;
        }

        const debugPanel = document.createElement('div');
        debugPanel.id = 'mobile-debug-panel';
        debugPanel.innerHTML = `
            <div class="debug-header">
                <span>Debug Console</span>
                <button id="debug-toggle">Hide</button>
                <button id="debug-clear">Clear</button>
            </div>
            <div id="debug-content"></div>
        `;

        debugPanel.style.cssText = `
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: rgba(0, 0, 0, 0.8);
            color: #00ff00;
            font-family: monospace;
            font-size: 12px;
            max-height: 40vh;
            overflow-y: auto;
            z-index: 9999;
            padding: 5px;
            border-top: 2px solid #444;
        `;

        document.body.appendChild(debugPanel);

        const toggleBtn = document.getElementById('debug-toggle');
        const content = document.getElementById('debug-content');
        toggleBtn.addEventListener('click', () => {
            if (content.style.display === 'none') {
                content.style.display = 'block';
                toggleBtn.textContent = 'Hide';
            } else {
                content.style.display = 'none';
                toggleBtn.textContent = 'Show';
            }
        });

        document.getElementById('debug-clear').addEventListener('click', () => {
            document.getElementById('debug-content').innerHTML = '';
        });

        const originalLog = console.log;
        const originalError = console.error;
        const originalWarn = console.warn;

        console.log = function() {
            originalLog.apply(console, arguments);

            const msg = Array.from(arguments).map(arg =>
                typeof arg === 'object' ? JSON.stringify(arg) : arg
            ).join(' ');

            appendToDebugPanel('LOG', msg);
        };

        console.error = function() {
            originalError.apply(console, arguments);
            const msg = Array.from(arguments).map(arg =>
                typeof arg === 'object' ? JSON.stringify(arg) : arg
            ).join(' ');
            appendToDebugPanel('ERROR', msg, 'red');
        };

        console.warn = function() {
            originalWarn.apply(console, arguments);
            const msg = Array.from(arguments).map(arg =>
                typeof arg === 'object' ? JSON.stringify(arg) : arg
            ).join(' ');
            appendToDebugPanel('WARN', msg, 'orange');
        };

        function appendToDebugPanel(level, msg, color = null) {
            const content = document.getElementById('debug-content');
            const entry = document.createElement('div');
            entry.innerHTML = `<span style="color: ${color || '#00ff00'}">[${level}]</span> ${msg}`;
            content.appendChild(entry);

            content.scrollTop = content.scrollHeight;

            while (content.children.length > 100) {
                content.removeChild(content.firstChild);
            }
        }

        this.logSpeechEvent = function(event, data) {
            appendToDebugPanel('SPEECH', `${event}: ${JSON.stringify(data)}`, '#00ffff');
        };
    }

    async initializeVoices() {
        // Try to load voices immediately
        this.voices = window.speechSynthesis.getVoices();

        // If no voices are available, wait for them to load
        if (this.voices.length === 0) {
            await new Promise(resolve => {
                window.speechSynthesis.onvoiceschanged = () => {
                    this.voices = window.speechSynthesis.getVoices();
                    this.voicesLoaded = true;
                    console.log('Voices loaded:', this.voices.length);
                    resolve();
                };
            });
        } else {
            this.voicesLoaded = true;
        }
    }

    initializeSpeechRecognition() {
        if ('webkitSpeechRecognition' in window) {
            console.log('Speech recognition is supported');
            this.recognition = new webkitSpeechRecognition();


            // Simplified settings for mobile
            if (this.mobileDevice) {
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.mobileRecognitionDelay = 1000;
                console.log('Using mobile settings for recognition');
            } else {
                this.recognition.continuous = true;
                this.recognition.interimResults = true;
                this.mobileRecognitionDelay = 300;
                console.log('Using desktop settings for recognition');
            }

            console.log('Recognition settings:', {
                continuous: this.recognition.continuous,
                interimResults: this.recognition.interimResults,
                lang: this.recognition.lang,
                maxAlternatives: this.recognition.maxAlternatives
            });


            this.recognition.lang = 'am-ET';
            this.recognition.maxAlternatives = 5;

            this.recognition.onstart = () => {
                console.log('Recognition started');
                if (this.logSpeechEvent) this.logSpeechEvent('onstart', {});
                const feedback = document.getElementById('speechFeedback');
                feedback.classList.add('listening-active');
                document.getElementById('spokenWord').textContent = '';
            };

            this.recognition.onresult = (event) => {
                if (this.logSpeechEvent) {
                    const results = [];
                    for (let i = 0; i < event.results.length; i++) {
                        const result = [];
                        for (let j = 0; j < event.results[i].length; j++) {
                            result.push({
                                transcript: event.results[i][j].transcript,
                                confidence: event.results[i][j].confidence
                            });
                        }
                        results.push(result);
                    }
                    this.logSpeechEvent('onresult', { results });
                }

                if (this.isSpeaking) {
                    console.log('Ignoring recognition result while speaking');
                    return;
                }

                const lastResultIndex = event.results.length - 1;
                const result = event.results[lastResultIndex];

                if (!result.isFinal) {
                    const interimTranscript = result[0].transcript;
                    document.getElementById('spokenWord').textContent = interimTranscript + '...';
                    return;
                }

                const spokenWord = result[0].transcript.trim();
                console.log('Final recognition result:', spokenWord);

                if (!spokenWord) {
                    console.log('Empty result detected, continuing recognition');
                    return;
                }

                document.getElementById('spokenWord').textContent = spokenWord;
                this.stopListening();
                this.validateSpokenWord(spokenWord);
            };

            this.recognition.onend = () => {
                console.log('Recognition ended');
                this.isRecognitionActive = false;
                const feedback = document.getElementById('speechFeedback');
                feedback.classList.remove('listening-active');

                if (this.isListening && !this.finalResultProcessed && !this.isSpeaking) {
                    console.log('Restarting recognition automatically...');
                    setTimeout(() => {
                        try {
                            this.recognition.start();
                            this.isRecognitionActive = true;
                        } catch (error) {
                            console.error('Error restarting recognition:', error);
                            if (this.manualListeningMode) {
                                this.showListeningOptions();
                            }
                        }
                    }, 300);
                    return;
                }

                if (this.manualListeningMode) {
                    this.showListeningOptions();
                }
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.isRecognitionActive = false;

                if (this.manualListeningMode) {
                    console.log('Manual mode - not auto-restarting after error');
                    this.showListeningOptions();
                    return;
                }

                if (this.pageVisible && this.isListening && !this.isSpeaking && this.isStarted) {
                    const delay = 2000;
                    console.log(`Attempting to restart recognition after error in ${delay}ms`);

                    setTimeout(() => {
                        this.startListening();
                    }, delay);
                }
            };
        } else {
            console.error('Speech recognition not supported in this browser');
        }
    }

    initializeEventListeners() {
        const nextWordBtn = document.getElementById('nextWordBtn');
        const stopBtn = document.getElementById('stopBtn');

        nextWordBtn.addEventListener('click', async () => {
            await this.loadRandomWord();
            await this.playWordAndListen();
        });

        stopBtn.addEventListener('click', () => {
            this.stopPractice();
        });

        // Add permission button handler
        const permissionBtn = document.getElementById('requestPermissionBtn');
        if (permissionBtn) {
            permissionBtn.addEventListener('click', () => {
                this.testMicrophonePermission();
                permissionBtn.textContent = 'Permission Requested';
                setTimeout(() => {
                    permissionBtn.style.display = 'none';
                }, 2000);
            });
        }
    }

    async loadRandomWord() {
        try {
            const params = new URLSearchParams();
            if (this.currentCategory) params.append('category_id', this.currentCategory);
            if (this.currentLevel) params.append('level', this.currentLevel);

            // Stop current activities
            this.stopListening();
            window.speechSynthesis.cancel();
            this.isSpeaking = false;

            const response = await fetch(`/api/random-amharic-word?${params.toString()}`);
            if (!response.ok) {
                throw new Error('Failed to fetch word');
            }

            const newWord = await response.json();
            if (!newWord) {
                console.error('No words available for selected category/level');
                return false;
            }

            // Clear current word first
            this.currentWord = null;

            // Small delay to ensure clean state
            await new Promise(resolve => setTimeout(resolve, 100));

            // Set new word
            this.currentWord = newWord;
            document.getElementById('amharicWord').textContent = this.currentWord.word;
            console.log('New word loaded:', this.currentWord.word);

            if (this.isStarted) {
                document.getElementById('speechFeedback').classList.add('active');
            }

            return true; // Return true if word was loaded successfully
        } catch (error) {
            console.error('Error loading word:', error);
            return false;
        }
    }

    async playWordAndListen() {
        if (!this.currentWord) {
            const wordLoaded = await this.loadRandomWord();
            if (!wordLoaded) {
                alert('No words available for the selected category and level');
                this.stopPractice();
                return;
            }
        }

        // Hide listening feedback while playing audio
        const speechFeedback = document.getElementById('speechFeedback');
        speechFeedback.classList.remove('active');

        // Stop any existing recognition
        this.stopListening();

        if (this.currentWord.audio_path) {
            const audioPath = `/audio/${this.currentWord.audio_path}`;
            console.log('Playing audio from:', audioPath);

            const audio = new Audio(audioPath);

            audio.onended = () => {
                console.log('Audio playback ended');
                setTimeout(() => {
                    speechFeedback.classList.add('active');
                    this.startListening();
                }, 500);
            };

            try {
                await audio.play();
            } catch (error) {
                console.error('Audio playback error:', error);
                this.useTextToSpeech();
            }
        } else {
            this.useTextToSpeech();
        }
    }

    useTextToSpeech() {
        if (!this.speechSynthesis || !this.currentWord) return;

        // Hide listening feedback while speaking
        const speechFeedback = document.getElementById('speechFeedback');
        speechFeedback.classList.remove('active');

        // Ensure we have voices loaded
        if (!this.voicesLoaded || this.voices.length === 0) {
            console.log('Reloading voices...');
            this.voices = window.speechSynthesis.getVoices();
        }

        // Stop any ongoing speech
        window.speechSynthesis.cancel();

        // Reset speaking state
        this.isSpeaking = true;
        this.stopListening();

        const wordToSpeak = this.currentWord.word;
        console.log('Preparing to speak:', wordToSpeak);

        const utterance = new SpeechSynthesisUtterance(wordToSpeak);
        utterance.lang = 'am-ET';
        utterance.rate = 0.8;
        utterance.pitch = 1.0;
        utterance.volume = 1.0;

        // Select voice
        const selectedVoice = this.voices.find(voice =>
            voice.lang === 'am-ET' ||
            voice.lang.includes('am') ||
            voice.lang.includes('eth')
        );

        if (selectedVoice) {
            utterance.voice = selectedVoice;
            console.log('Using voice:', selectedVoice.name);
        }

        // Set up event handlers
        utterance.onstart = () => {
            console.log('Speech started:', wordToSpeak);
            this.isSpeaking = true;
        };

        utterance.onend = () => {
            console.log('Speech ended:', wordToSpeak);
            this.isSpeaking = false;
            setTimeout(() => {
                // Show listening feedback after speech ends
                document.getElementById('speechFeedback').classList.add('active');

                // Show buttons for all devices
                this.showListeningOptions();
            }, 1000);
        };

        utterance.onerror = (event) => {
            console.error('Speech error:', event);
            this.isSpeaking = false;
            this.retryTextToSpeech(wordToSpeak);
        };

        // Speak
        try {
            window.speechSynthesis.speak(utterance);
        } catch (error) {
            console.error('Speech failed:', error);
            this.isSpeaking = false;
            this.retryTextToSpeech(wordToSpeak);
        }
    }

    retryTextToSpeech(word) {
        if (word === this.currentWord.word) {
            console.log('Retrying speech...');
            setTimeout(() => this.useTextToSpeech(), 1000);
        } else {
            console.log('Word changed, not retrying');
            this.startListening();
        }
    }

    startListening() {
        if (!this.recognition) {
            console.error('Speech recognition not initialized');
            return;
        }

        if (this.isSpeaking) {
            console.log('Currently speaking, not starting recognition');
            return;
        }

        if (!this.pageVisible) {
            console.log('Page not visible, not starting recognition');
            return;
        }

        if (this.isRecognitionActive) {
            console.log('Recognition already active, skipping start');
            return;
        }

        try {
            console.log('Starting recognition');
            this.isListening = true;
            this.isRecognitionActive = true;
            this.finalResultProcessed = false;

            const feedback = document.getElementById('speechFeedback');
            feedback.classList.add('listening-active');
            document.getElementById('spokenWord').textContent = '';

            setTimeout(() => {
                if (this.isListening) {
                    try {
                        this.recognition.start();
                        console.log('Recognition actually started');
                    } catch (e) {
                        console.error('Failed to start recognition', e);
                        this.showListeningOptions();
                    }
                }
            }, this.mobileRecognitionDelay);

        } catch (error) {
            console.error('Error starting recognition:', error);
            this.isRecognitionActive = false;
            this.isListening = false;
        }
    }

    stopListening() {
        if (this.recognition) {
            try {
                this.isListening = false;
                this.isRecognitionActive = false;
                this.recognition.abort(); // Use abort() instead of stop()
                console.log('Recognition aborted');
            } catch (error) {
                console.error('Error stopping recognition:', error);
            }
        }
    }

    async validateSpokenWord(spokenWord) {
        console.log('Validating spoken word:', spokenWord);
        console.log('Current word:', this.currentWord.word);
        console.log('Valid transliterations:', this.currentWord.transliterations);

        // Clean up the spoken word
        const cleanSpokenWord = this.cleanWord(spokenWord);

        // Check against all valid transliterations
        const isCorrect = this.currentWord.transliterations.some(trans => {
            const cleanTrans = this.cleanWord(trans);
            const match = cleanTrans === cleanSpokenWord;
            console.log(`Comparing: "${cleanSpokenWord}" with "${cleanTrans}" - Match: ${match}`);
            return match;
        });

        console.log('Is correct:', isCorrect);

        if (isCorrect) {
            this.showSuccessFeedback();

            // Wait for success animation
            await new Promise(resolve => setTimeout(resolve, 2000));

            // Clear spoken word
            document.getElementById('spokenWord').textContent = '';

            // Load new word and wait for it to complete
            await this.loadRandomWord();

            // Now play the new word's audio
            this.playWordAndListen();
        } else {
            this.showErrorFeedback();
            document.getElementById('spokenWord').textContent = '';
        }
    }

    cleanWord(word) {
        return word
            .toLowerCase()
            .trim()
            .replace(/[.,\/#!$%\^&\*;:{}=\-_`~()]/g, '') // Remove punctuation
            .replace(/\s+/g, ' ') // Replace multiple spaces with single space
            .replace(/[᾿ʼ']/g, '') // Remove special quotes and marks
            .normalize('NFD') // Normalize unicode characters
            .replace(/[\u0300-\u036f]/g, ''); // Remove diacritics
    }

    showSuccessFeedback() {
        const feedback = document.createElement('div');
        feedback.className = 'feedback feedback-success active';

        // Create multiple fireworks
        const colors = ['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF'];
        const positions = [
            { x: '20%', y: '20%' },
            { x: '80%', y: '20%' },
            { x: '50%', y: '50%' },
            { x: '20%', y: '80%' },
            { x: '80%', y: '80%' }
        ];

        positions.forEach((pos, index) => {
            const firework = document.createElement('div');
            firework.className = 'firework';
            firework.style.left = pos.x;
            firework.style.top = pos.y;
            firework.style.setProperty('--firework-color', colors[index % colors.length]);

            // Create multiple bursts with delays
            for (let i = 0; i < 3; i++) {
                setTimeout(() => {
                    const burst = firework.cloneNode(true);
                    feedback.appendChild(burst);

                    // Remove each burst after animation
                    setTimeout(() => burst.remove(), 1000);
                }, i * 300);
            }
        });

        document.body.appendChild(feedback);

        // Add success message
        const message = document.createElement('div');
        message.className = 'success-message';
        message.textContent = window.translations.excellent;
        message.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 2rem;
            font-weight: bold;
            text-align: center;
            z-index: 101;
            text-shadow: 0 0 10px rgba(255,255,255,0.5);
        `;
        feedback.appendChild(message);

        // Remove feedback after animation
        setTimeout(() => {
            feedback.classList.add('fade-out');
            setTimeout(() => feedback.remove(), 300);
        }, 2000);
    }

    showErrorFeedback() {
        // Stop listening immediately
        this.stopListening();

        const feedback = document.createElement('div');
        feedback.className = 'feedback feedback-error active';

        const messageContainer = document.createElement('div');
        messageContainer.className = 'error-message';

        // Get the spoken word from the display
        const spokenWordText = document.getElementById('spokenWord').textContent;

        // Only show what was spoken if there's actual content
        if (spokenWordText && spokenWordText.trim() !== '') {
            // Show the incorrect word
            const wrongWord = document.createElement('div');
            wrongWord.className = 'wrong-word';
            wrongWord.innerHTML = `${window.translations.you_said}<br><strong>"${spokenWordText}"</strong>`;
            messageContainer.appendChild(wrongWord);
        } else {
            // Show a "didn't hear you" message
            const noSpeechMessage = document.createElement('div');
            noSpeechMessage.className = 'wrong-word';
            noSpeechMessage.textContent = "Didn't hear your voice clearly.";
            messageContainer.appendChild(noSpeechMessage);
        }

        // Try again message
        const tryAgain = document.createElement('div');
        tryAgain.className = 'try-again';
        tryAgain.textContent = window.translations.try_again;

        messageContainer.appendChild(tryAgain);
        feedback.appendChild(messageContainer);

        document.body.appendChild(feedback);

        // Show error message, wait, then play audio
        setTimeout(() => {
            feedback.classList.add('fade-out');
            setTimeout(() => {
                feedback.remove();
                // Make sure we're not listening before playing audio
                this.stopListening();
                this.isRecognitionActive = false;
                this.isListening = false;
                // Play the word again after error message fades
                this.playWordAndListen();
            }, 300);
        }, 2000);
    }

    setupVisibilityHandling() {
        // Handle page visibility changes
        document.addEventListener('visibilitychange', () => {
            this.pageVisible = document.visibilityState === 'visible';
            console.log('Page visibility changed:', this.pageVisible);

            if (!this.pageVisible) {
                // Stop recognition when page is hidden
                console.log('Page hidden - stopping recognition');
                this.stopListening();
            } else if (this.isStarted && !this.isSpeaking) {
                // Only restart if we're in active state and not speaking
                console.log('Page visible again - resuming after delay');
                setTimeout(() => {
                    this.startListening();
                }, 1000);
            }
        });

        // Handle mobile focus/blur events
        window.addEventListener('blur', () => {
            console.log('Window lost focus - stopping recognition');
            this.stopListening();
        });

        window.addEventListener('focus', () => {
            if (this.isStarted && !this.isSpeaking && this.pageVisible) {
                console.log('Window gained focus - resuming after delay');
                setTimeout(() => {
                    this.startListening();
                }, 1000);
            }
        });
    }

    showListeningOptions() {
        // Remove existing container if it exists
        const existingContainer = document.querySelector('.mobile-buttons-container');
        if (existingContainer) {
            existingContainer.remove();
        }

        // Only show listening options on mobile
        if (this.mobileDevice) {
            const buttonContainer = document.createElement('div');
            buttonContainer.className = 'mobile-buttons-container';

            // Create listen again button (for all devices)
            const listenAgainBtn = document.createElement('button');
            listenAgainBtn.className = 'listen-again-btn';
            listenAgainBtn.innerHTML = '<i class="fas fa-volume-up"></i> Listen Again';
            buttonContainer.appendChild(listenAgainBtn);

            // Only create speak button on mobile devices
            if (this.mobileDevice) {
                // Create speak button for mobile only
                const speakBtn = document.createElement('button');
                speakBtn.className = 'manual-listen-btn';
                speakBtn.innerHTML = '<i class="fas fa-microphone"></i> Speak';
                buttonContainer.appendChild(speakBtn);

                // Add click event for speak button
                speakBtn.addEventListener('click', () => {
                    this.startListening();
                });

                // Only add note on mobile and on first appearance
                if (!sessionStorage.getItem('instructionShown')) {
                    const noteElement = document.createElement('div');
                    noteElement.className = 'recognition-note';
                    noteElement.textContent = 'Speak clearly after tapping the microphone';

                    // Position in the speech feedback area
                    const speechFeedback = document.getElementById('speechFeedback');
                    speechFeedback.appendChild(noteElement);

                    // Mark that we've shown the instruction
                    sessionStorage.setItem('instructionShown', 'true');
                }
            } else {
                // On desktop, start listening automatically after a short delay
                setTimeout(() => {
                    this.startListening();
                }, 1000);
            }

            // Position in the speech feedback area
            const speechFeedback = document.getElementById('speechFeedback');
            speechFeedback.appendChild(buttonContainer);

            // Update the status text
            const statusElement = speechFeedback.querySelector('.speech-status');
            if (statusElement) {
                statusElement.textContent = this.mobileDevice ? 'Ready' : 'Listening...';
            }

            // Add click event for listen again button
            listenAgainBtn.addEventListener('click', () => {
                // First stop any ongoing recognition
                this.stopListening();

                // Play the word again
                if (this.currentWord.audio_path) {
                    const audioPath = `/audio/${this.currentWord.audio_path}`;
                    const audio = new Audio(audioPath);
                    audio.play()
                        .catch(error => {
                            console.error('Error playing audio:', error);
                            this.useTextToSpeech();
                        });
                } else {
                    this.useTextToSpeech();
                }
            });
        }
    }

    testMicrophonePermission() {
        if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
            navigator.mediaDevices.getUserMedia({ audio: true })
                .then(stream => {
                    console.log('Microphone permission granted');
                    // Stop the stream immediately
                    stream.getTracks().forEach(track => track.stop());
                })
                .catch(err => {
                    console.error('Microphone permission denied:', err);
                });
        } else {
            console.error('getUserMedia not supported on this device');
        }
    }

    initializeSettingsControls() {
        const categoryButtons = document.querySelectorAll('.category-btn');
        const levelButtons = document.querySelector('.level-buttons');
        const levelButtonsContainer = document.querySelector('.level-buttons-container');
        const practiceArea = document.getElementById('practiceArea');
        const practiceSettings = document.getElementById('practiceSettings');
        const randomPracticeBtn = document.getElementById('randomPracticeBtn');

        // Add random practice button handler
        randomPracticeBtn.addEventListener('click', () => {
            this.currentCategory = null;
            this.currentLevel = null;

            this.isStarted = true;
            practiceSettings.style.display = 'none';
            practiceArea.style.display = 'block';
            document.getElementById('speechFeedback').classList.add('active');
            this.playWordAndListen();
        });

        categoryButtons.forEach(button => {
            button.addEventListener('click', async () => {
                // Remove selected class from all category buttons
                categoryButtons.forEach(btn => btn.classList.remove('selected'));
                // Add selected class to clicked button
                button.classList.add('selected');

                this.currentCategory = button.dataset.category;

                try {
                    const response = await fetch(`/api/categories/${this.currentCategory}/levels`);
                    if (!response.ok) throw new Error('Failed to fetch levels');

                    const levels = await response.json();

                    // Create level buttons
                    levelButtonsContainer.innerHTML = levels.map(level =>
                        `<button class="level-btn" data-level="${level}">Level ${level}</button>`
                    ).join('');

                    // Show level buttons
                    levelButtons.style.display = 'block';

                    // Add click handlers to level buttons
                    document.querySelectorAll('.level-btn').forEach(levelBtn => {
                        levelBtn.addEventListener('click', () => {
                            this.currentLevel = levelBtn.dataset.level;
                            this.isStarted = true;
                            practiceSettings.style.display = 'none';
                            practiceArea.style.display = 'block';
                            document.getElementById('speechFeedback').classList.add('active');
                            this.playWordAndListen();
                        });
                    });
                } catch (error) {
                    console.error('Error fetching levels:', error);
                }
            });
        });
    }

    stopPractice() {
        this.isStarted = false;
        this.stopListening();
        window.speechSynthesis.cancel();

        // Clear all feedback and state
        this.currentWord = null;
        this.finalResultProcessed = false;
        this.isRecognitionActive = false;
        this.isListening = false;
        this.isSpeaking = false;

        // Remove any existing mobile buttons container
        const existingContainer = document.querySelector('.mobile-buttons-container');
        if (existingContainer) {
            existingContainer.remove();
        }

        // Show/hide appropriate sections
        document.getElementById('practiceSettings').style.display = 'block';
        document.getElementById('practiceArea').style.display = 'none';

        // Hide and clear feedback
        const speechFeedback = document.getElementById('speechFeedback');
        speechFeedback.classList.remove('active');
        speechFeedback.classList.remove('listening-active');
        document.getElementById('spokenWord').textContent = '';
        document.getElementById('amharicWord').textContent = '';

        // Force stop recognition
        if (this.recognition) {
            try {
                this.recognition.abort();
            } catch (e) {
                console.log('Recognition abort error:', e);
            }
        }
    }

    updateButtonText() {
        const actionBtn = document.getElementById('actionBtn');
        if (!this.isStarted) {
            actionBtn.querySelector('span').textContent = 'Start Practice';
        } else {
            actionBtn.querySelector('span').textContent = 'Next Word';
        }
    }
}

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AmharicPractice();
});
