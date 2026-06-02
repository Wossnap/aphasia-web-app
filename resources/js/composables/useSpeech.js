import { ref, onUnmounted } from 'vue';

export function useSpeech({ speechDriver, onResult, onStateChange }) {
    const isMobile = /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    const useGoogle = speechDriver === 'google';

    // Per-word engine override ('v1'|'v2') for the current word, or null to let the
    // server fall back to the live .env default. Set in playWordAndListen().
    let activeVersion = null;

    let recognition      = null;
    let persistentStream = null;
    let voices           = [];
    let isListening      = false;
    let isBlocked        = false;
    let recStopped       = true;

    // Google-mode state
    let mediaRecorder    = null;
    let audioChunks      = [];
    let vadAnalyser      = null;
    let vadAudioCtx      = null;
    let vadFrame         = null;
    let vadTimeout       = null;
    let googleRecording  = false;
    let stoppedByUser    = false;
    let restartTimer     = null;   // pending Google restart, cancellable on stop
    let wantListening    = false;  // user intent — single source of truth for restarts

    const micReady = ref(false);

    function setState(s) { onStateChange(s); }

    // ─── Voices ───────────────────────────────────────────────────────────────
    function loadVoices() {
        voices = window.speechSynthesis?.getVoices() ?? [];
        if (voices.length === 0) {
            window.speechSynthesis.onvoiceschanged = () => {
                voices = window.speechSynthesis.getVoices();
            };
        }
    }
    loadVoices();

    // ─── Browser speech recognition ───────────────────────────────────────────
    function initRecognition() {
        if (useGoogle) return;
        const SR = window.webkitSpeechRecognition ?? window.SpeechRecognition;
        if (!SR) return;

        recognition = new SR();
        recognition.lang            = 'am-ET';
        recognition.continuous      = true;
        recognition.interimResults  = true;
        recognition.maxAlternatives = 5;

        recognition.onstart = () => { recStopped = false; };

        recognition.onresult = (event) => {
            if (isBlocked || !isListening) return;
            const last = event.results[event.results.length - 1];
            if (!last.isFinal) return;
            const spoken = last[0].transcript.trim();
            if (!spoken) return;
            stopListening();
            onResult(spoken);
        };

        recognition.onend = () => {
            recStopped = true;
            if (isListening && !isBlocked) {
                setTimeout(safeStart, isMobile ? 800 : 200);
            }
        };

        recognition.onerror = (e) => {
            recStopped = true;
            if (e.error === 'not-allowed') { setState('mic-denied'); return; }
            if (isListening && !isBlocked) setTimeout(safeStart, 1500);
        };
    }
    initRecognition();

    function safeStart() {
        if (!recognition || !recStopped || !isListening || isBlocked) return;
        try {
            recognition.start();
        } catch (_) {
            setTimeout(safeStart, 300);
        }
    }

    // ─── Google Cloud speech recognition ──────────────────────────────────────
    async function startGoogleListening() {
        if (googleRecording) return;
        // Bail if the user stopped (or is in playback) since this was scheduled.
        if (!wantListening || isBlocked) return;

        clearTimeout(restartTimer);
        restartTimer = null;

        try {
            if (!persistentStream) {
                persistentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                micReady.value = true;
            }

            vadAudioCtx  = new (window.AudioContext || window.webkitAudioContext)();
            const source = vadAudioCtx.createMediaStreamSource(persistentStream);
            vadAnalyser  = vadAudioCtx.createAnalyser();
            vadAnalyser.minDecibels         = -60;
            vadAnalyser.smoothingTimeConstant = 0.8;
            source.connect(vadAnalyser);

            audioChunks  = [];
            const opts   = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
                ? { mimeType: 'audio/webm;codecs=opus' } : {};
            mediaRecorder = new MediaRecorder(persistentStream, opts);

            mediaRecorder.ondataavailable = (e) => {
                if (e.data.size > 0) audioChunks.push(e.data);
            };

            mediaRecorder.onstop = () => processGoogleAudio();

            // The mic acquisition above can await; re-check the user didn't stop meanwhile.
            if (!wantListening || isBlocked) {
                if (vadAudioCtx?.state !== 'closed') { vadAudioCtx.close(); vadAudioCtx = null; }
                vadAnalyser = null;
                return;
            }

            googleRecording = true;
            stoppedByUser   = false;
            isListening     = true;

            mediaRecorder.start();
            monitorSilence();

            // Safety: stop after 10 s max
            vadTimeout = setTimeout(() => stopGoogleListening(), 10000);

        } catch (err) {
            console.error('Google listening error:', err);
            googleRecording = false;
        }
    }

    function monitorSilence() {
        if (!googleRecording || !vadAnalyser) return;
        const buf = new Uint8Array(vadAnalyser.frequencyBinCount);

        function getRms() {
            vadAnalyser.getByteFrequencyData(buf);
            return Math.sqrt(buf.reduce((s, v) => s + v * v, 0) / buf.length);
        }

        // Sample ambient noise for 300ms to set a device-specific threshold
        const calibrationSamples = [];
        const calibrationStart = Date.now();

        const calibrate = () => {
            if (!googleRecording) return;
            calibrationSamples.push(getRms());
            if (Date.now() - calibrationStart < 300) {
                requestAnimationFrame(calibrate);
            } else {
                const ambientRms = calibrationSamples.reduce((a, b) => a + b, 0) / calibrationSamples.length;
                // Speech threshold: at least 1.8x above ambient, minimum of 8
                const speechThreshold = Math.max(8, ambientRms * 1.8);
                startVad(speechThreshold);
            }
        };
        calibrate();

        function startVad(threshold) {
            let silenceStart = null;
            let hasSpoken    = false;

            const check = () => {
                if (!googleRecording) return;
                const rms = getRms();

                if (rms > threshold) {
                    hasSpoken    = true;
                    silenceStart = null;
                } else if (hasSpoken) {
                    silenceStart ??= Date.now();
                    if (Date.now() - silenceStart > 1500) {
                        stopGoogleListening();
                        return;
                    }
                }
                vadFrame = requestAnimationFrame(check);
            };
            check();
        }
    }

    function stopGoogleListening(byUser = false) {
        if (!googleRecording) return;
        googleRecording = false;
        stoppedByUser   = byUser;

        clearTimeout(vadTimeout);
        cancelAnimationFrame(vadFrame);

        if (mediaRecorder?.state !== 'inactive') mediaRecorder.stop();
        if (vadAudioCtx?.state !== 'closed') { vadAudioCtx.close(); vadAudioCtx = null; }
        vadAnalyser = null;
    }

    async function processGoogleAudio() {
        if (stoppedByUser) { isListening = false; return; }
        if (audioChunks.length === 0) return;

        setState('processing');

        const blob     = new Blob(audioChunks, { type: 'audio/webm' });
        const formData = new FormData();
        formData.append('audio', blob, 'recording.webm');
        // Only send a version when this word overrides the engine; otherwise the
        // server picks the current .env default (no stale page-baked value).
        if (activeVersion) formData.append('version', activeVersion);

        try {
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const res  = await fetch('/api/transcribe', {
                method:  'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' },
                body:    formData,
            });

            const text = await res.text();
            if (!res.ok) { restartGoogleIfNeeded(); return; }

            // User stopped while this request was in flight — drop the result.
            if (!wantListening) return;

            const data = JSON.parse(text);
            if (data.results?.[0]?.alternatives?.[0]?.transcript) {
                const spoken = data.results[0].alternatives[0].transcript.trim();
                onResult(spoken);
            } else {
                restartGoogleIfNeeded();
            }
        } catch (_) {
            restartGoogleIfNeeded();
        }
    }

    function restartGoogleIfNeeded() {
        if (wantListening && !isBlocked) {
            clearTimeout(restartTimer);
            restartTimer = setTimeout(startGoogleListening, 500);
        }
    }

    // ─── Mic ──────────────────────────────────────────────────────────────────
    async function openMic() {
        if (persistentStream) return;
        try {
            persistentStream = await navigator.mediaDevices.getUserMedia({ audio: true });
            micReady.value = true;
        } catch (_) {
            micReady.value = false;
        }
    }

    function closeMic() {
        persistentStream?.getTracks().forEach(t => t.stop());
        persistentStream = null;
        micReady.value   = false;
    }

    // ─── Unified listen control ───────────────────────────────────────────────
    function startListening() {
        if (isBlocked) return;
        wantListening = true;
        if (useGoogle) {
            startGoogleListening();
        } else {
            if (!recognition) return;
            isListening = true;
            if (recStopped) safeStart();
        }
    }

    function stopListening() {
        wantListening = false;
        isListening   = false;
        clearTimeout(restartTimer);
        restartTimer = null;
        if (useGoogle) {
            stopGoogleListening(true);
        } else {
            try { recognition?.abort(); } catch (_) {}
        }
    }

    // ─── Audio playback ───────────────────────────────────────────────────────
    function playAudio(path) {
        return new Promise((resolve) => {
            const audio = new Audio(path);
            audio.onended = resolve;
            audio.onerror = resolve;
            audio.play().catch(resolve);
        });
    }

    // ─── TTS ──────────────────────────────────────────────────────────────────
    function speak(text) {
        return new Promise((resolve) => {
            window.speechSynthesis.cancel();
            const utt    = new SpeechSynthesisUtterance(text);
            utt.lang     = 'am-ET';
            utt.rate     = 0.8;
            utt.volume   = 1.0;
            const voice  = voices.find(v =>
                v.lang === 'am-ET' || v.lang.startsWith('am') || v.lang.includes('eth'));
            if (voice) utt.voice = voice;
            utt.onend  = resolve;
            utt.onerror = resolve;
            window.speechSynthesis.speak(utt);
        });
    }

    // ─── Main flow ────────────────────────────────────────────────────────────
    async function playWordAndListen(word) {
        stopListening();
        window.speechSynthesis.cancel();

        // Per-word override wins; otherwise null → server uses the live .env default.
        activeVersion = (word?.engine === 'v1' || word?.engine === 'v2')
            ? word.engine
            : null;

        isBlocked = true;
        setState('playing');

        if (isMobile || useGoogle) await openMic();

        if (word.audio_path) {
            await playAudio(`/audio/${word.audio_path}`);
        } else {
            await speak(word.word);
        }

        await delay(400);
        isBlocked = false;
        setState('listening');
        startListening();
    }

    function replayWord(word) { return playWordAndListen(word); }

    function stopAll() {
        stopListening();
        isBlocked = false;
        window.speechSynthesis.cancel();
        closeMic();
        setState('idle');
    }

    async function requestMicPermission() {
        await openMic();
        return micReady.value;
    }

    onUnmounted(stopAll);

    return { micReady, playWordAndListen, replayWord, stopAll, requestMicPermission };
}

function delay(ms) { return new Promise(r => setTimeout(r, ms)); }
