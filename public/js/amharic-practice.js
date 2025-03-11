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

        // Initialize voices first
        this.initializeVoices().then(() => {
            this.initializeSpeechRecognition();
            this.initializeEventListeners();
            this.setupVisibilityHandling();
            this.loadRandomWord();
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
        const actionBtn = document.getElementById('actionBtn');
        actionBtn.addEventListener('click', () => {
            if (!this.isStarted) {
                this.isStarted = true;
                actionBtn.textContent = window.translations.next_word;
                actionBtn.classList.remove('start-btn');
                actionBtn.classList.add('next-word-btn');
                document.getElementById('speechFeedback').classList.add('active');
                this.playWordAndListen();
            } else {
                this.loadRandomWord();
                this.playWordAndListen();
            }
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
            // Stop current activities
            this.stopListening();
            window.speechSynthesis.cancel();
            this.isSpeaking = false;

            const response = await fetch('/api/random-amharic-word');
            const newWord = await response.json();

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

            // Return a promise that resolves when everything is ready
            return new Promise(resolve => setTimeout(resolve, 100));
        } catch (error) {
            console.error('Error loading word:', error);
        }
    }

    playWordAndListen() {
        if (!this.currentWord) {
            console.error('No current word available');
            return;
        }

        // Hide listening feedback while playing audio
        const speechFeedback = document.getElementById('speechFeedback');
        speechFeedback.classList.remove('active');

        // Stop any existing recognition
        this.stopListening();

        if (this.currentWord.audio_path) {
            const audioPath = `/audio/${this.currentWord.audio_path}`;
            console.log('Playing audio from:', audioPath, 'for word:', this.currentWord.word);

            const audio = new Audio(audioPath);

            audio.onerror = (error) => {
                console.error('Error loading audio file:', error);
                console.log('Falling back to text-to-speech');
                this.useTextToSpeech();
            };

            audio.onloadstart = () => {
                console.log('Loading audio file for:', this.currentWord.word);
            };

            audio.play()
                .then(() => {
                    console.log('Audio playback started for:', this.currentWord.word);

                    audio.onended = () => {
                        console.log('Audio playback ended for:', this.currentWord.word);
                        setTimeout(() => {
                            // Show listening feedback after audio ends
                            speechFeedback.classList.add('active');

                            // Show buttons for all devices
                            this.showListeningOptions();
                        }, 500);
                    };
                })
                .catch((error) => {
                    console.error('Audio playback error:', error);
                    this.useTextToSpeech();
                });
        } else {
            console.log('No audio file available for:', this.currentWord.word);
            setTimeout(() => this.useTextToSpeech(), 100);
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

            const manualBtn = document.querySelector('.manual-listen-btn');
            if (manualBtn) manualBtn.remove();

            const existingNote = document.querySelector('.recognition-note');
            if (existingNote) existingNote.remove();

            const feedback = document.getElementById('speechFeedback');
            feedback.classList.add('listening-active');
            document.getElementById('spokenWord').textContent = '';

            if (this.mobileDevice) {
                const statusElement = feedback.querySelector('.speech-status');
                if (statusElement) {
                    statusElement.textContent = 'Listening...';
                }

                if ('vibrate' in navigator) {
                    navigator.vibrate(50);
                }

                feedback.classList.add('mobile-listening');
            }

            setTimeout(() => {
                if (this.isListening) {
                    try {
                        this.recognition.start();
                        console.log('Recognition actually started');

                        if (this.mobileDevice) {
                            setTimeout(() => {
                                if (this.isRecognitionActive && !this.finalResultProcessed) {
                                    console.log('Mobile timeout - stopping recognition');
                                    this.stopListening();
                                    this.showListeningOptions();
                                }
                            }, 7000);
                        }
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

            if (this.manualListeningMode) {
                this.showListeningOptions();
            }
        }
    }

    stopListening() {
        try {
            console.log('Stopping recognition');
            this.isListening = false;
            this.isRecognitionActive = false;
            this.recognition.stop();
        } catch (error) {
            console.error('Error stopping recognition:', error);
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
        // Remove existing buttons and note if there are any
        const existingBtn = document.querySelector('.manual-listen-btn');
        if (existingBtn) existingBtn.remove();

        const existingListenAgainBtn = document.querySelector('.listen-again-btn');
        if (existingListenAgainBtn) existingListenAgainBtn.remove();

        const existingNote = document.querySelector('.recognition-note');
        if (existingNote) existingNote.remove();

        // Create buttons container
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
}

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AmharicPractice();
});
