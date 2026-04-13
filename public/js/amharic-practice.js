class AmharicPractice {
    constructor() {
        this.speechSynthesis = window.speechSynthesis;
        this.recognition = null;
        this.isListening = false;
        this.isRecognitionActive = false;
        this.finalResultProcessed = false;
        this.currentWord = null;
        this.currentCategory = null;
        this.currentLevel = null;
        this.isStarted = false;
        this.isSpeaking = false;
        this.pageVisible = true;
        this.voices = [];
        this.voicesLoaded = false;
        this.mobileDevice = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        this.mobileRecognitionDelay = this.mobileDevice ? 1000 : 100;
        this.mobileRestartCount = 0;
        this.persistentMicStream = null;

        // Configuration
        this.speechDriver = window.appConfig?.speechDriver || 'browser';
        
        // Google Cloud Mode Properties
        this.mediaRecorder = null;
        this.audioContext = null;
        this.vadAnalyser = null;
        this.vadAnimationFrame = null;
        this.vadMaxTimeout = null;
        this.audioChunks = [];
        this.isGoogleRecording = false;
        this.googleStoppedByUser = false;

        // Pagination state
        this.categories = [];
        this.currentCategoryPage = 0;
        this.currentLevelPage = 0;
        this.itemsPerPage = 10; // Increased for better UX, fewer page turns
        this.currentLevels = [];

        // Initialize after DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', () => this.initialize());
        } else {
            this.initialize();
        }
    }

    initialize() {
        // Load categories data first
        this.loadCategoriesData();

        this.setupMobileDebugger();
        this.initializeVoices();
        this.initializeSpeechRecognition();
        this.initializeEventListeners();
        this.setupVisibilityHandling();
        this.testMicrophonePermission();
        this.initializeSettingsControls();
        this.initializePagination();
        this.initializePWAInstall();
    }

    initializePWAInstall() {
        this.deferredPrompt = null;
        const installBtn = document.getElementById('installAppBtn');

        window.addEventListener('beforeinstallprompt', (e) => {
            // Prevent Chrome 67 and earlier from automatically showing the prompt
            e.preventDefault();
            // Stash the event so it can be triggered later.
            this.deferredPrompt = e;
            // Update UI to show the install button
            if (installBtn) {
                installBtn.style.display = 'flex';
            }
        });

        if (installBtn) {
            installBtn.addEventListener('click', () => {
                if (!this.deferredPrompt) return;
                
                // Show the prompt
                this.deferredPrompt.prompt();
                
                // Wait for the user to respond to the prompt
                this.deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        console.log('User accepted the install prompt');
                    } else {
                        console.log('User dismissed the install prompt');
                    }
                    this.deferredPrompt = null;
                    installBtn.style.display = 'none';
                });
            });
        }

        window.addEventListener('appinstalled', (evt) => {
            console.log('App was installed');
            if (installBtn) {
                installBtn.style.display = 'none';
            }
        });
    }

    setupMobileDebugger() {
        // Debug panel removed for production — use remote debugging instead
        this.logSpeechEvent = null;
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
        if (this.speechDriver === 'google') {
            console.log('Using Google Cloud Speech API driver');
            return;
        }

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
                // Mic is truly open - reveal full listening UI at once
                feedback.classList.add('active');
                feedback.classList.add('listening-active');
                document.getElementById('spokenWord').textContent = '';
                const statusEl = feedback.querySelector('.speech-status');
                if (statusEl) statusEl.textContent = 'Listening...';
                // Inject buttons that were prepared while mic was warming up
                this.showListeningButtons();
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
                    }, this.mobileDevice ? 1000 : 300);
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

            // Load GIF if available
            this.loadWordGif();

            // Load image if available
            this.loadWordImage();

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
            // On mobile, open persistent mic before playing audio so indicator is solid
            if (this.mobileDevice) {
                await this.startPersistentMic();
            }

            const audioPath = `/audio/${this.currentWord.audio_path}`;
            console.log('Playing audio from:', audioPath);

            const audio = new Audio(audioPath);

            audio.onended = () => {
                console.log('Audio playback ended');
                // Prepare button container and start mic warm-up; UI stays unchanged until onstart
                setTimeout(() => {
                    this.showListeningOptions();
                }, 500);
            };

            try {
                await audio.play();
            } catch (error) {
                console.error('Audio playback error:', error);
                this.useTextToSpeech();
            }
        } else {
            // On mobile, open persistent mic before TTS so indicator is solid
            if (this.mobileDevice) {
                await this.startPersistentMic();
            }
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
            // Prepare button container and start mic warm-up; UI stays unchanged until onstart
            setTimeout(() => {
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
        if (this.speechDriver === 'google') {
            this.startGoogleListening();
            return;
        }

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
            this.mobileRestartCount = 0;
            this.finalResultProcessed = false;

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
        if (this.speechDriver === 'google') {
            this.stopGoogleListening();
            return;
        }

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

    async startGoogleListening() {
        if (this.isGoogleRecording) return;
        
        try {
            // Get microphone stream if not already persistent
            if (!this.persistentMicStream) {
                this.persistentMicStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            }
            
            // Setup AudioContext for VAD
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const source = this.audioContext.createMediaStreamSource(this.persistentMicStream);
            this.vadAnalyser = this.audioContext.createAnalyser();
            this.vadAnalyser.minDecibels = -60;
            this.vadAnalyser.smoothingTimeConstant = 0.8;
            source.connect(this.vadAnalyser);
            
            // Setup MediaRecorder
            this.audioChunks = [];
            
            // Prefer opus if supported by MediaRecorder
            const options = MediaRecorder.isTypeSupported('audio/webm;codecs=opus') 
                ? { mimeType: 'audio/webm;codecs=opus' } 
                : {};
                
            this.mediaRecorder = new MediaRecorder(this.persistentMicStream, options);
            
            this.mediaRecorder.ondataavailable = (event) => {
                if (event.data.size > 0) {
                    this.audioChunks.push(event.data);
                }
            };
            
            this.mediaRecorder.onstop = () => {
                this.processGoogleAudio();
            };
            
            // UI Updates
            this.isListening = true;
            this.isRecognitionActive = true;
            this.isGoogleRecording = true;
            
            const feedback = document.getElementById('speechFeedback');
            feedback.classList.add('active', 'listening-active');
            document.getElementById('spokenWord').textContent = '...';
            
            const statusEl = feedback.querySelector('.speech-status');
            if (statusEl) statusEl.textContent = 'Listening...';
            this.showListeningButtons();
            
            this.mediaRecorder.start();
            this.monitorSilence();
            
            // Safety timeout (10 seconds max)
            this.vadMaxTimeout = setTimeout(() => {
                console.log('Max recording limit reached');
                this.stopGoogleListening();
            }, 10000);
            
        } catch (error) {
            console.error('Error starting Google listening:', error);
            this.isGoogleRecording = false;
            this.showListeningOptions();
        }
    }
    
    monitorSilence() {
        if (!this.isGoogleRecording || !this.vadAnalyser) return;
        
        const bufferLength = this.vadAnalyser.frequencyBinCount;
        const dataArray = new Uint8Array(bufferLength);
        
        let silenceStart = null;
        let hasSpoken = false;
        const silenceThreshold = 15; // out of 255
        const silenceDurationToStop = 1500; // 1.5 seconds of silence
        
        const checkVolume = () => {
            if (!this.isGoogleRecording) return;
            
            this.vadAnalyser.getByteFrequencyData(dataArray);
            
            // Calculate RMS
            let sum = 0;
            for (let i = 0; i < bufferLength; i++) {
                sum += dataArray[i] * dataArray[i];
            }
            const rms = Math.sqrt(sum / bufferLength);
            
            if (rms > silenceThreshold) {
                hasSpoken = true;
                silenceStart = null; // Reset silence timer
            } else if (hasSpoken) {
                // If it's quiet and we have already spoken
                if (silenceStart === null) {
                    silenceStart = Date.now();
                } else if (Date.now() - silenceStart > silenceDurationToStop) {
                    console.log('Silence detected, stopping recording');
                    this.stopGoogleListening();
                    return; // Stop monitoring
                }
            }
            
            this.vadAnimationFrame = requestAnimationFrame(checkVolume);
        };
        
        checkVolume();
    }
    
    stopGoogleListening(byUser = false) {
        if (!this.isGoogleRecording) return;
        
        this.isGoogleRecording = false;
        this.googleStoppedByUser = byUser;
        
        if (this.mediaRecorder && this.mediaRecorder.state !== 'inactive') {
            this.mediaRecorder.stop();
        }
        
        if (this.vadMaxTimeout) {
            clearTimeout(this.vadMaxTimeout);
            this.vadMaxTimeout = null;
        }
        
        if (this.vadAnimationFrame) {
            cancelAnimationFrame(this.vadAnimationFrame);
            this.vadAnimationFrame = null;
        }
        
        if (byUser) {
            document.getElementById('spokenWord').textContent = '';
            const feedback = document.getElementById('speechFeedback');
            feedback.classList.remove('listening-active', 'active');
            const statusEl = feedback.querySelector('.speech-status');
            if (statusEl) statusEl.textContent = '';
        } else {
            document.getElementById('spokenWord').textContent = 'Processing...';
            const feedback = document.getElementById('speechFeedback');
            feedback.classList.remove('listening-active');
            const statusEl = feedback.querySelector('.speech-status');
            if (statusEl) statusEl.textContent = 'Processing...';
        }
    }
    
    async processGoogleAudio() {
        // Don't process if user explicitly stopped
        if (this.googleStoppedByUser) {
            this.googleStoppedByUser = false;
            this.isRecognitionActive = false;
            return;
        }
        
        if (this.audioChunks.length === 0) {
            this.isRecognitionActive = false;
            this.showListeningOptions();
            return;
        }
        
        const blob = new Blob(this.audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', blob, 'recording.webm');
        
        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            
            const response = await fetch('/api/transcribe', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json'
                },
                body: formData
            });
            
            // Always read body as text first so we can log the real error
            const rawText = await response.text();
            
            if (!response.ok) {
                console.error(`Transcription server error [HTTP ${response.status}]:`, rawText);
                this.isRecognitionActive = false;
                document.getElementById('spokenWord').textContent = `Server error ${response.status}`;
                this.showListeningOptions();
                return;
            }
            
            let data;
            try {
                data = JSON.parse(rawText);
            } catch (parseErr) {
                console.error('Failed to parse transcription response as JSON:', rawText);
                this.isRecognitionActive = false;
                this.showListeningOptions();
                return;
            }
            
            this.isRecognitionActive = false;
            
            if (data.results && data.results.length > 0 && data.results[0].alternatives.length > 0) {
                const spokenWord = data.results[0].alternatives[0].transcript.trim();
                console.log('Google Cloud Final Result:', spokenWord);
                document.getElementById('spokenWord').textContent = spokenWord;
                this.validateSpokenWord(spokenWord);
            } else {
                console.log('Empty result detected from Google Cloud');
                this.showErrorFeedback();
            }
            
        } catch (error) {
            console.error('Error sending audio to server:', error);
            this.isRecognitionActive = false;
            this.showListeningOptions();
        }
    }

    async validateSpokenWord(spokenWord) {
        if (!this.currentWord) {
            console.log('No current word to validate against');
            return;
        }

        const spokenLower = spokenWord.toLowerCase();
        console.log('Validating:', spokenLower, 'against transliterations:', this.currentWord.transliterations);

        // Check if any transliteration is found within the spoken text
        const isCorrect = this.currentWord.transliterations.some(trans =>
            spokenLower.includes(trans.toLowerCase())
        );

        const feedback = document.getElementById('speechFeedback');
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
            for (let i = 0; i < 5; i++) {
                setTimeout(() => {
                    const burst = firework.cloneNode(true);
                    feedback.appendChild(burst);

                    // Remove each burst after animation
                    setTimeout(() => burst.remove(), 1500);
                }, i * 200);
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

        // Show listening options for all devices (not just mobile)
        const buttonContainer = document.createElement('div');
        buttonContainer.className = 'mobile-buttons-container';

        // Create listen again button (for all devices)
        const listenAgainBtn = document.createElement('button');
        listenAgainBtn.className = 'listen-again-btn';
        listenAgainBtn.innerHTML = '<i class="fas fa-volume-up"></i> Listen Again';
        buttonContainer.appendChild(listenAgainBtn);

        // Auto-start listening for all devices after a short delay
        setTimeout(() => {
            this.startListening();
        }, 1000);

        // Store the button container to be injected when the mic is actually ready
        this._pendingButtonContainer = buttonContainer;

        // Add click event for listen again button with improved microphone handling
        listenAgainBtn.addEventListener('click', () => {
            // First stop any ongoing recognition
            this.stopListening();

            // Clear any existing feedback
            const speechFeedback = document.getElementById('speechFeedback');
            speechFeedback.classList.remove('listening-active');
            document.getElementById('spokenWord').textContent = '';

            // Hide listening feedback while playing audio
            speechFeedback.classList.remove('active');

            // Play the word again
            if (this.currentWord && this.currentWord.audio_path) {
                const audioPath = `/audio/${this.currentWord.audio_path}`;
                const audio = new Audio(audioPath);

                // Set up audio event handlers
                audio.onended = () => {
                    console.log('Audio playback ended - restarting listening');
                    // Prepare button container and start mic warm-up; UI stays unchanged until onstart
                    setTimeout(() => {
                        this.showListeningOptions();
                    }, 500);
                };

                audio.onerror = (error) => {
                    console.error('Audio playback error:', error);
                    // Fallback to text-to-speech
                    this.useTextToSpeech();
                };

                // Start playing audio
                audio.play()
                    .catch(error => {
                        console.error('Error playing audio:', error);
                        this.useTextToSpeech();
                    });
            } else {
                // Use text-to-speech if no audio file
                this.useTextToSpeech();
            }
        });
    }

    showListeningButtons() {
        if (!this._pendingButtonContainer) return;
        const speechFeedback = document.getElementById('speechFeedback');
        // Remove any previously appended container
        const existing = speechFeedback.querySelector('.mobile-buttons-container');
        if (existing) existing.remove();
        speechFeedback.appendChild(this._pendingButtonContainer);
        this._pendingButtonContainer = null;
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

    async startPersistentMic() {
        // Only needed on mobile — keeps the green mic indicator solid
        if (this.persistentMicStream) return;

        try {
            this.persistentMicStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            console.log('Persistent mic stream opened');
        } catch (err) {
            console.error('Failed to open persistent mic stream:', err);
        }
    }

    stopPersistentMic() {
        if (this.persistentMicStream) {
            this.persistentMicStream.getTracks().forEach(track => track.stop());
            this.persistentMicStream = null;
            console.log('Persistent mic stream closed');
        }
    }

    initializePagination() {
        // Debug: Check if categories are loaded
        console.log('Initializing pagination with categories:', this.categories);

        // Initialize category pagination
        if (this.categories.length > 0) {
            this.displayCategoryPage(0);
        } else {
            console.log('No categories available yet, will display when loaded');
        }

        // Set up pagination event listeners
        const categoryPrevBtn = document.getElementById('categoryPrevBtn');
        const categoryNextBtn = document.getElementById('categoryNextBtn');
        const levelPrevBtn = document.getElementById('levelPrevBtn');
        const levelNextBtn = document.getElementById('levelNextBtn');

        if (categoryPrevBtn) {
            categoryPrevBtn.addEventListener('click', () => {
                if (this.currentCategoryPage > 0) {
                    this.currentCategoryPage--;
                    this.displayCategoryPage(this.currentCategoryPage);
                }
            });
        }

        if (categoryNextBtn) {
            categoryNextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil(this.categories.length / this.itemsPerPage);
                if (this.currentCategoryPage < totalPages - 1) {
                    this.currentCategoryPage++;
                    this.displayCategoryPage(this.currentCategoryPage);
                }
            });
        }

        if (levelPrevBtn) {
            levelPrevBtn.addEventListener('click', () => {
                if (this.currentLevelPage > 0) {
                    this.currentLevelPage--;
                    this.displayLevelPage(this.currentLevelPage);
                }
            });
        }

        if (levelNextBtn) {
            levelNextBtn.addEventListener('click', () => {
                const totalPages = Math.ceil(this.currentLevels.length / this.itemsPerPage);
                if (this.currentLevelPage < totalPages - 1) {
                    this.currentLevelPage++;
                    this.displayLevelPage(this.currentLevelPage);
                }
            });
        }
    }

    displayCategoryPage(page) {
        if (!this.categories || this.categories.length === 0) {
            console.log('No categories to display');
            return;
        }

        const startIndex = page * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageCategories = this.categories.slice(startIndex, endIndex);
        const totalPages = Math.ceil(this.categories.length / this.itemsPerPage);

        console.log('Displaying category page', page);
        console.log('Categories data:', this.categories);
        console.log('Page categories:', pageCategories);
        console.log('Total pages:', totalPages);

        // Update category buttons
        const categoryButtons = document.getElementById('categoryButtons');
        if (!categoryButtons) {
            console.error('categoryButtons element not found');
            return;
        }

        categoryButtons.innerHTML = pageCategories.map(category =>
            `<button class="category-btn" data-category="${category.id}">
                ${category.name}
            </button>`
        ).join('');

        // Update page info
        const categoryPageInfo = document.getElementById('categoryPageInfo');
        if (categoryPageInfo) {
            categoryPageInfo.textContent = `Page ${page + 1} of ${totalPages}`;
        }

        // Update pagination buttons
        const categoryPrevBtn = document.getElementById('categoryPrevBtn');
        const categoryNextBtn = document.getElementById('categoryNextBtn');
        if (categoryPrevBtn) {
            categoryPrevBtn.disabled = page === 0;
        }
        if (categoryNextBtn) {
            categoryNextBtn.disabled = page === totalPages - 1;
        }

        // Add event listeners to new category buttons
        categoryButtons.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', async () => {
                console.log('Category button clicked:', button.dataset.category);
                // Remove selected class from all category buttons
                categoryButtons.querySelectorAll('.category-btn').forEach(btn => btn.classList.remove('selected'));
                // Add selected class to clicked button
                button.classList.add('selected');

                this.currentCategory = button.dataset.category;

                try {
                    const response = await fetch(`/api/categories/${this.currentCategory}/levels`);
                    if (!response.ok) throw new Error('Failed to fetch levels');

                    this.currentLevels = await response.json();
                    console.log('Levels loaded:', this.currentLevels);
                    this.currentLevelPage = 0;
                    this.displayLevelPage(0);

                    // Show level buttons
                    const levelButtons = document.getElementById('levelButtons');
                    if (levelButtons) {
                        levelButtons.style.display = 'block';
                    }
                } catch (error) {
                    console.error('Error fetching levels:', error);
                }
            });
        });

        console.log('Category page displayed successfully');
    }

    displayLevelPage(page) {
        const startIndex = page * this.itemsPerPage;
        const endIndex = startIndex + this.itemsPerPage;
        const pageLevels = this.currentLevels.slice(startIndex, endIndex);
        const totalPages = Math.ceil(this.currentLevels.length / this.itemsPerPage);

        console.log('Levels data:', this.currentLevels);
        console.log('Page levels:', pageLevels);
        console.log('Total pages:', totalPages);

        // Update level buttons
        const levelButtonsContainer = document.getElementById('levelButtonsContainer');
        if (levelButtonsContainer) {
            levelButtonsContainer.innerHTML = pageLevels.map(level =>
                `<button class="level-btn" data-level="${level}">Level ${level}</button>`
            ).join('');
        }

        // Update page info
        const levelPageInfo = document.getElementById('levelPageInfo');
        if (levelPageInfo) {
            levelPageInfo.textContent = `Page ${page + 1} of ${totalPages}`;
        }

        // Update pagination buttons
        const levelPrevBtn = document.getElementById('levelPrevBtn');
        const levelNextBtn = document.getElementById('levelNextBtn');
        if (levelPrevBtn) {
            levelPrevBtn.disabled = page === 0;
        }
        if (levelNextBtn) {
            levelNextBtn.disabled = page === totalPages - 1;
        }

        // Add event listeners to new level buttons
        if (levelButtonsContainer) {
            levelButtonsContainer.querySelectorAll('.level-btn').forEach(levelBtn => {
                levelBtn.addEventListener('click', () => {
                    this.currentLevel = levelBtn.dataset.level;
                    this.isStarted = true;
                    // Enable scrolling when practice starts
                    document.body.classList.add('practice-active');
                    document.getElementById('practiceSettings').style.display = 'none';
                    document.getElementById('practiceArea').style.display = 'block';
                    document.getElementById('speechFeedback').classList.add('active');
                    this.playWordAndListen();
                });
            });
        }
    }

    initializeSettingsControls() {
        const practiceArea = document.getElementById('practiceArea');
        const practiceSettings = document.getElementById('practiceSettings');
        const randomPracticeBtn = document.getElementById('randomPracticeBtn');

        // Add random practice button handler
        randomPracticeBtn.addEventListener('click', () => {
            this.currentCategory = null;
            this.currentLevel = null;

            this.isStarted = true;
            // Enable scrolling when practice starts
            document.body.classList.add('practice-active');
            practiceSettings.style.display = 'none';
            practiceArea.style.display = 'block';
            document.getElementById('speechFeedback').classList.add('active');
            this.playWordAndListen();
        });
    }

    stopPractice() {
        this.isStarted = false;
        this.stopListening();
        this.stopPersistentMic();
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

        // Disable scrolling when returning to level selector
        document.body.classList.remove('practice-active');

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
        
        // Cleanup Google mode properties
        if (this.vadMaxTimeout) {
            clearTimeout(this.vadMaxTimeout);
            this.vadMaxTimeout = null;
        }
        if (this.vadAnimationFrame) {
            cancelAnimationFrame(this.vadAnimationFrame);
            this.vadAnimationFrame = null;
        }
        if (this.audioContext && this.audioContext.state !== 'closed') {
            this.audioContext.close();
            this.audioContext = null;
        }
        this.isGoogleRecording = false;
    }

    updateButtonText() {
        const actionBtn = document.getElementById('actionBtn');
        if (!this.isStarted) {
            actionBtn.querySelector('span').textContent = 'Start Practice';
        } else {
            actionBtn.querySelector('span').textContent = 'Next Word';
        }
    }

    loadWordGif() {
        const gifContainer = document.getElementById('wordGifContainer');
        const gifImage = document.getElementById('wordGif');
        const gifWrapper = document.querySelector('.gif-wrapper');

        // For debugging - show what path we have
        console.log('Current word:', this.currentWord);
        console.log('Current word gif path:', this.currentWord ? this.currentWord.gif_path : 'No word loaded');

        // Reset any previous states
        gifWrapper.classList.remove('loading', 'placeholder');

        if (!this.currentWord || !this.currentWord.gif_path) {
            // Set default "no image" placeholder instead of hiding
            gifContainer.style.display = 'flex';
            gifImage.src = '/images/no-image-placeholder.svg'; // Changed to SVG
            gifWrapper.classList.add('placeholder');
            return;
        }

        // Show GIF container
        gifContainer.style.display = 'flex';

        // Add loading state
        gifWrapper.classList.add('loading');

        // Set GIF source - Make sure the path starts correctly
        // Trim any leading slashes from the gif_path to ensure we don't double up
        const cleanPath = this.currentWord.gif_path.replace(/^\/+/, '');
        const gifPath = `/gifs/${cleanPath}`;

        console.log('Attempting to load GIF from:', gifPath);

        // Create a new Image object to test if the file exists
        const testImage = new Image();
        testImage.onload = () => {
            // If the image loads successfully, set it as the source
            gifImage.src = gifPath;
            gifWrapper.classList.remove('loading');
            console.log('GIF loaded successfully');
        };

        testImage.onerror = () => {
            console.error('Failed to load GIF at path:', gifPath);
            // Set default "broken image" placeholder
            gifImage.src = '/images/broken-image.svg'; // Changed to SVG
            gifWrapper.classList.add('placeholder');
            gifWrapper.classList.remove('loading');
        };

        // Start loading the test image
        testImage.src = gifPath;
    }

    loadWordImage() {
        const imageContainer = document.getElementById('wordImageContainer');
        const imageElement = document.getElementById('wordImage');
        const imageWrapper = document.querySelector('.image-wrapper');

        if (!imageContainer || !imageElement || !imageWrapper) {
            console.error('Missing image container elements:', {
                container: !!imageContainer,
                image: !!imageElement,
                wrapper: !!imageWrapper
            });
            return;
        }

        console.log('Image container:', imageContainer);
        console.log('Image element:', imageElement);

        // Reset any previous states
        imageWrapper.classList.remove('loading', 'placeholder');

        if (!this.currentWord || !this.currentWord.image_path) {
            // Set default "no image" placeholder
            imageContainer.style.display = 'flex';
            imageElement.src = '/images/no-image-placeholder.svg';
            imageWrapper.classList.add('placeholder');
            return;
        }

        // Show image container
        imageContainer.style.display = 'flex';

        // Add loading state
        imageWrapper.classList.add('loading');

        // Set image source
        const cleanPath = this.currentWord.image_path.replace(/^\/+/, '');
        const imagePath = `/images/${cleanPath}`;
        console.log('Image path:', imagePath);

        console.log('Attempting to load image from:', imagePath);

        // Create a new Image object to test if the file exists
        const testImage = new Image();
        testImage.onload = () => {
            // If the image loads successfully, set it as the source
            imageElement.src = imagePath;
            imageWrapper.classList.remove('loading');
            console.log('Image loaded successfully');
        };

        testImage.onerror = () => {
            console.error('Failed to load image at path:', imagePath);
            // Set default "broken image" placeholder
            imageElement.src = '/images/broken-image.svg';
            imageWrapper.classList.add('placeholder');
            imageWrapper.classList.remove('loading');
        };

        // Start loading the test image
        testImage.src = imagePath;
    }

    loadCategoriesData() {
        // Try to load categories from window.categoriesData first
        console.log('Loading categories data...');
        console.log('window.categoriesData:', window.categoriesData);

        if (window.categoriesData && window.categoriesData.length > 0) {
            this.categories = window.categoriesData;
            console.log('Categories loaded from window:', this.categories);
            // Initialize pagination immediately
            this.displayCategoryPage(0);
        } else {
            console.log('No categories in window.categoriesData, loading from API');
            this.loadCategoriesFromAPI();
        }
    }

    loadCategoriesFromAPI() {
        console.log('Fetching categories from API...');
        fetch('/api/categories')
            .then(response => {
                console.log('API response status:', response.status);
                return response.json();
            })
            .then(categories => {
                console.log('Categories loaded from API:', categories);
                this.categories = categories;
                // Re-initialize pagination after loading categories
                if (this.categories.length > 0) {
                    this.displayCategoryPage(0);
                } else {
                    console.error('No categories received from API');
                }
            })
            .catch(error => {
                console.error('Failed to load categories from API:', error);
            });
    }

    loadCategoriesFromDOM() {
        // This is now handled by loadCategoriesFromAPI
        this.loadCategoriesFromAPI();
    }
}

// Initialize the application when the DOM is ready
document.addEventListener('DOMContentLoaded', function () {
    console.log('DOM loaded, initializing AmharicPractice...');
    window.amharicPractice = new AmharicPractice();
});

// Fallback for browsers that don't support DOMContentLoaded
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        if (!window.amharicPractice) {
            console.log('Fallback: Initializing AmharicPractice...');
            window.amharicPractice = new AmharicPractice();
        }
    });
} else {
    // DOM is already loaded
    if (!window.amharicPractice) {
        console.log('DOM already loaded, initializing AmharicPractice...');
        window.amharicPractice = new AmharicPractice();
    }
}
