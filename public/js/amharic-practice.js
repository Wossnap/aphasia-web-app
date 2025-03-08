class AmharicPractice {
    constructor() {
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

        // Initialize voices first
        this.initializeVoices().then(() => {
            this.initializeSpeechRecognition();
            this.initializeEventListeners();
            this.loadRandomWord();
        });
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
            this.recognition = new webkitSpeechRecognition();
            this.recognition.continuous = false;
            this.recognition.interimResults = false;
            this.recognition.lang = 'am-ET';

            // Increase recognition confidence threshold
            this.recognition.maxAlternatives = 5;

            this.recognition.onresult = (event) => {
                // Ignore results if we're speaking
                if (this.isSpeaking) {
                    console.log('Ignoring recognition result while speaking');
                    return;
                }

                const results = Array.from(event.results[0]);
                results.sort((a, b) => b.confidence - a.confidence);

                const spokenWord = results[0].transcript;
                console.log('Recognition results:', results.map(r => ({
                    transcript: r.transcript,
                    confidence: r.confidence
                })));

                document.getElementById('spokenWord').textContent = spokenWord;
                this.validateSpokenWord(spokenWord);
            };

            this.recognition.onend = () => {
                console.log('Recognition ended');
                this.isRecognitionActive = false;

                if (this.isListening) {
                    // Add a small delay before restarting
                    setTimeout(() => {
                        this.startListening();
                    }, 300);
                }
            };

            this.recognition.onerror = (event) => {
                console.error('Speech recognition error:', event.error);
                this.isListening = false;
                this.isRecognitionActive = false;

                // Restart listening after error with longer delay
                setTimeout(() => {
                    this.startListening();
                }, 2000);
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
                            this.startListening();
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
                speechFeedback.classList.add('active');
                this.startListening();
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
        // Don't start listening if we're speaking
        if (this.isSpeaking) {
            console.log('Currently speaking, not starting recognition');
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
            this.recognition.start();
        } catch (error) {
            console.error('Error starting recognition:', error);
            this.isRecognitionActive = false;
            this.isListening = false;

            if (!this.isSpeaking) {
                setTimeout(() => {
                    this.startListening();
                }, 1000);
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

        // Show the incorrect word
        const wrongWord = document.createElement('div');
        wrongWord.className = 'wrong-word';
        wrongWord.innerHTML = `${window.translations.you_said}<br><strong>"${document.getElementById('spokenWord').textContent}"</strong>`;

        // Try again message
        const tryAgain = document.createElement('div');
        tryAgain.className = 'try-again';
        tryAgain.textContent = window.translations.try_again;

        messageContainer.appendChild(wrongWord);
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
}

// Initialize when the DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new AmharicPractice();
});
