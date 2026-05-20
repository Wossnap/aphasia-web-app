<template>
    <div class="app">
        <!-- ── Settings screen ── -->
        <transition name="slide-left">
            <div v-if="screen === 'settings'" class="screen settings-screen" key="settings">
                <div class="settings-inner">
                    <h1 class="app-title">Amharic Practice</h1>
                    <p class="app-subtitle">Hear a word. Say it back.</p>

                    <!-- Mode toggle -->
                    <div class="mode-toggle">
                        <button
                            class="mode-btn"
                            :class="{ 'mode-btn-active': practiceMode === 'random' }"
                            @click="practiceMode = 'random'"
                        >
                            <i class="fas fa-random"></i> Shuffle
                        </button>
                        <button
                            class="mode-btn"
                            :class="{ 'mode-btn-active': practiceMode === 'consecutive' }"
                            @click="practiceMode = 'consecutive'"
                        >
                            <i class="fas fa-list-ol"></i> In Order
                        </button>
                    </div>

                    <button class="btn btn-primary btn-xl" @click="startRandom">
                        <i class="fas fa-play btn-icon"></i>
                        Start Practice
                    </button>

                    <div class="divider"><span>or choose a topic</span></div>

                    <div class="category-grid">
                        <button
                            v-for="cat in categories"
                            :key="cat.id"
                            class="category-card"
                            :class="{ active: selectedCategory?.id === cat.id }"
                            @click="selectCategory(cat)"
                        >
                            {{ cat.name }}
                        </button>
                    </div>

                    <transition name="fade">
                        <div v-if="levels.length > 0" class="level-section">
                            <p class="level-label">Choose a level</p>
                            <div class="level-grid">
                                <button
                                    v-for="lvl in levels"
                                    :key="lvl"
                                    class="level-card"
                                    @click="startLevel(lvl)"
                                >
                                    Level {{ lvl }}
                                </button>
                            </div>
                        </div>
                    </transition>

                    <transition name="fade">
                        <button v-if="showInstall" class="btn btn-install" @click="installApp">
                            <i class="fas fa-download btn-icon"></i>
                            Install App
                        </button>
                    </transition>
                </div>
            </div>
        </transition>

        <!-- ── Practice screen ── -->
        <transition name="slide-right">
            <div v-if="screen === 'practice'" class="screen practice-screen" key="practice">
                <!-- Status bar -->
                <div class="status-bar" :class="statusClass">
                    <i :class="statusIcon" class="status-icon"></i>
                    <span class="status-text">{{ statusText }}</span>
                </div>

                <!-- Word + media -->
                <div class="word-section">
                    <!-- Single media (default) -->
                    <div class="media-card" v-if="currentWord && !mediaUrl2">
                        <img
                            v-if="mediaUrl"
                            :src="mediaUrl"
                            :alt="currentWord.word"
                            class="word-media"
                            @error="mediaUrl = null"
                        />
                        <div v-else class="media-placeholder">
                            <i class="fas fa-image"></i>
                        </div>
                    </div>

                    <!-- Two GIFs side by side -->
                    <div class="media-dual" v-if="currentWord && mediaUrl2">
                        <div class="media-card media-card-sm">
                            <img :src="mediaUrl" :alt="currentWord.word" class="word-media" @error="mediaUrl = null" />
                        </div>
                        <div class="media-card media-card-sm">
                            <img :src="mediaUrl2" :alt="currentWord.word" class="word-media" @error="mediaUrl2 = null" />
                        </div>
                    </div>

                    <div class="word-display">
                        <span class="amharic-word">{{ currentWord?.word ?? '…' }}</span>
                    </div>

                    <div v-if="spokenWord" class="spoken-display">
                        <span class="spoken-label">You said:</span>
                        <span class="spoken-word">{{ spokenWord }}</span>
                    </div>
                </div>

                <!-- Controls -->
                <div class="controls">
                    <button
                        class="btn btn-secondary btn-lg"
                        :disabled="speechState === 'playing' || speechState === 'loading'"
                        @click="listenAgain"
                    >
                        <i class="fas fa-volume-up btn-icon"></i>
                        Listen Again
                    </button>

                    <button
                        class="btn btn-primary btn-lg"
                        :disabled="speechState === 'loading'"
                        @click="nextWord"
                    >
                        <i class="fas fa-forward btn-icon"></i>
                        Next Word
                    </button>

                    <button class="btn btn-danger btn-lg" @click="goBack">
                        <i class="fas fa-stop btn-icon"></i>
                        Stop
                    </button>
                </div>
            </div>
        </transition>

        <!-- ── Feedback overlay ── -->
        <transition name="fade">
            <div v-if="feedback" class="feedback-overlay" :class="`feedback-${feedback}`">
                <div class="feedback-content">
                    <div v-if="feedback === 'success'" class="feedback-emoji">🎉</div>
                    <div v-else class="feedback-emoji">💪</div>
                    <div class="feedback-message">
                        {{ feedback === 'success' ? translations.excellent : translations.try_again }}
                    </div>
                    <div v-if="feedback === 'error' && spokenWord" class="feedback-spoken">
                        "{{ spokenWord }}"
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useSpeech } from '../composables/useSpeech';

const props = defineProps({
    categories:   { type: Array,  default: () => [] },
    speechDriver: { type: String, default: 'browser' },
    translations: { type: Object, default: () => ({}) },
});

// ─── App state ────────────────────────────────────────────────────────────────
const screen          = ref('settings');
const speechState     = ref('idle');   // idle | loading | playing | listening | processing
const currentWord     = ref(null);
const spokenWord      = ref('');
const feedback        = ref(null);     // null | 'success' | 'error'
const selectedCategory = ref(null);
const levels          = ref([]);
const currentCategory = ref(null);
const currentLevel    = ref(null);
const practiceMode    = ref('random');
const mediaUrl        = ref(null);
const mediaUrl2       = ref(null); // second visual: image when gif+image both exist

// PWA install
const deferredInstall = ref(null);
const showInstall     = ref(false);

// ─── Speech composable ────────────────────────────────────────────────────────
const { playWordAndListen, replayWord, stopAll, requestMicPermission } = useSpeech({
    speechDriver: props.speechDriver,
    onStateChange: (s) => { speechState.value = s; },
    onResult:      handleSpokenResult,
});

// ─── Status bar ───────────────────────────────────────────────────────────────
const statusIcon = computed(() => ({
    idle:       'fas fa-microphone-slash',
    loading:    'fas fa-spinner fa-spin',
    playing:    'fas fa-volume-up',
    listening:  'fas fa-microphone',
    processing: 'fas fa-spinner fa-spin',
}[speechState.value] ?? 'fas fa-microphone'));

const statusText = computed(() => ({
    idle:       'Ready',
    loading:    'Loading…',
    playing:    'Listen carefully…',
    listening:  'Now say it!',
    processing: 'Checking…',
}[speechState.value] ?? ''));

const statusClass = computed(() => ({
    idle:       'status-idle',
    loading:    'status-loading',
    playing:    'status-playing',
    listening:  'status-listening',
    processing: 'status-processing',
}[speechState.value] ?? 'status-idle'));

// ─── Category / level selection ───────────────────────────────────────────────
async function selectCategory(cat) {
    selectedCategory.value = cat;
    levels.value = [];
    try {
        const res = await fetch(`/api/categories/${cat.id}/levels`);
        levels.value = await res.json();
    } catch (_) {}
}

function startRandom() {
    currentCategory.value = null;
    currentLevel.value    = null;
    launchPractice();
}

function startLevel(lvl) {
    currentCategory.value = selectedCategory.value?.id ?? null;
    currentLevel.value    = lvl;
    launchPractice();
}

async function launchPractice() {
    screen.value = 'practice';
    speechState.value = 'loading';
    await loadWord();
    await playWordAndListen(currentWord.value);
}

// ─── Word loading ─────────────────────────────────────────────────────────────
async function loadWord() {
    speechState.value = 'loading';
    mediaUrl.value  = null;
    mediaUrl2.value = null;
    const params = new URLSearchParams({ mode: practiceMode.value });
    if (currentCategory.value) params.set('category_id', currentCategory.value);
    if (currentLevel.value)    params.set('level', currentLevel.value);
    if (practiceMode.value === 'consecutive' && currentWord.value) {
        params.set('last_id', currentWord.value.id);
    }

    const res  = await fetch(`/api/random-amharic-word?${params}`);
    const word = await res.json();

    if (!word) {
        screen.value = 'settings';
        return;
    }

    currentWord.value = word;
    spokenWord.value  = '';
    setMedia(word);
}

function setMedia(word) {
    if (word.gif_path) {
        mediaUrl.value  = `/gifs/${word.gif_path.replace(/^\/+/, '')}`;
        // Show the image alongside the GIF when both exist
        mediaUrl2.value = word.image_path ? `/images/${word.image_path.replace(/^\/+/, '')}` : null;
    } else if (word.image_path) {
        mediaUrl.value  = `/images/${word.image_path.replace(/^\/+/, '')}`;
        mediaUrl2.value = null;
    } else {
        mediaUrl.value  = null;
        mediaUrl2.value = null;
    }
}

// ─── User actions ─────────────────────────────────────────────────────────────
async function nextWord() {
    await loadWord();
    await playWordAndListen(currentWord.value);
}

async function listenAgain() {
    spokenWord.value = '';
    await replayWord(currentWord.value);
}

function goBack() {
    stopAll();
    screen.value      = 'settings';
    currentWord.value = null;
    spokenWord.value  = '';
    feedback.value    = null;
    speechState.value = 'idle';
    levels.value      = [];
    selectedCategory.value = null;
}

// ─── Speech result handling ───────────────────────────────────────────────────
async function handleSpokenResult(spoken) {
    spokenWord.value  = spoken;
    speechState.value = 'processing';

    const isCorrect = currentWord.value?.transliterations?.some(t =>
        spoken.toLowerCase().includes(t.toLowerCase())
    ) ?? false;

    if (isCorrect) {
        feedback.value = 'success';
        await delay(2000);
        feedback.value = null;
        await loadWord();
        await playWordAndListen(currentWord.value);
    } else {
        feedback.value = 'error';
        await delay(2000);
        feedback.value   = null;
        spokenWord.value = '';
        await replayWord(currentWord.value);
    }
}

// ─── PWA install ─────────────────────────────────────────────────────────────
function installApp() {
    if (!deferredInstall.value) return;
    deferredInstall.value.prompt();
    deferredInstall.value.userChoice.then(() => {
        deferredInstall.value = null;
        showInstall.value     = false;
    });
}

// ─── Lifecycle ───────────────────────────────────────────────────────────────
onMounted(() => {
    requestMicPermission();

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstall.value = e;
        showInstall.value     = true;
    });

    if ('speechSynthesis' in window) window.speechSynthesis.getVoices();
});

function delay(ms) {
    return new Promise(r => setTimeout(r, ms));
}
</script>

<style scoped>
/* ── Reset & base ─────────────────────────────────────────────── */
.app {
    min-height: 100dvh;
    background: #0F172A;
    color: #fff;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    overflow: hidden;
    position: relative;
}

.screen {
    min-height: 100dvh;
    width: 100%;
    display: flex;
    flex-direction: column;
    position: absolute;
    inset: 0;
}

/* ── Settings screen ──────────────────────────────────────────── */
.settings-screen {
    background: linear-gradient(160deg, #0F172A 0%, #1E1B4B 100%);
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}

.settings-inner {
    max-width: 480px;
    margin: 0 auto;
    padding: 3rem 1.5rem 5rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 1.5rem;
}

.app-title {
    font-size: 2.4rem;
    font-weight: 800;
    text-align: center;
    background: linear-gradient(135deg, #A78BFA, #60A5FA);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    margin: 0;
}

.app-subtitle {
    font-size: 1.2rem;
    color: rgba(255,255,255,0.6);
    text-align: center;
    margin: 0;
}

/* ── Mode toggle ──────────────────────────────────────────────── */
.mode-toggle {
    display: flex;
    width: 100%;
    background: rgba(255,255,255,0.07);
    border-radius: 1rem;
    padding: 4px;
    gap: 4px;
}

.mode-btn {
    flex: 1;
    padding: 0.9rem;
    font-size: 1.1rem;
    font-weight: 700;
    font-family: inherit;
    border: none;
    border-radius: 0.75rem;
    cursor: pointer;
    background: transparent;
    color: rgba(255,255,255,0.5);
    transition: all 0.2s;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.mode-btn-active {
    background: linear-gradient(135deg, #8B5CF6, #3B82F6);
    color: #fff;
    box-shadow: 0 4px 12px rgba(139,92,246,0.35);
}

/* ── Buttons ──────────────────────────────────────────────────── */
.btn {
    border: none;
    border-radius: 1rem;
    cursor: pointer;
    font-family: inherit;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    transition: transform 0.15s, box-shadow 0.15s;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    width: 100%;
}

.btn:active { transform: scale(0.97); }
.btn:disabled { opacity: 0.5; cursor: not-allowed; }

.btn-xl {
    padding: 1.4rem 2rem;
    font-size: 1.4rem;
    background: linear-gradient(135deg, #8B5CF6, #3B82F6);
    color: #fff;
    box-shadow: 0 8px 24px rgba(139,92,246,0.4);
    border-radius: 1.2rem;
}

.btn-lg {
    padding: 1.2rem 1.5rem;
    font-size: 1.2rem;
}

.btn-primary {
    background: linear-gradient(135deg, #8B5CF6, #3B82F6);
    color: #fff;
    box-shadow: 0 6px 20px rgba(139,92,246,0.35);
}

.btn-secondary {
    background: rgba(255,255,255,0.1);
    color: #fff;
    border: 2px solid rgba(255,255,255,0.2);
}

.btn-danger {
    background: linear-gradient(135deg, #EF4444, #DC2626);
    color: #fff;
    box-shadow: 0 6px 20px rgba(239,68,68,0.3);
}

.btn-install {
    background: linear-gradient(135deg, #10B981, #059669);
    color: #fff;
    padding: 1rem 1.5rem;
    font-size: 1.1rem;
    box-shadow: 0 6px 20px rgba(16,185,129,0.3);
}

.btn-icon { font-size: 1.1em; }

/* ── Divider ──────────────────────────────────────────────────── */
.divider {
    display: flex;
    align-items: center;
    width: 100%;
    gap: 0.75rem;
    color: rgba(255,255,255,0.4);
    font-size: 1rem;
}
.divider::before, .divider::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.15);
}

/* ── Category grid ────────────────────────────────────────────── */
.category-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.75rem;
    width: 100%;
}

.category-card {
    padding: 1.1rem 0.75rem;
    font-size: 1.1rem;
    font-weight: 600;
    background: rgba(255,255,255,0.07);
    color: #fff;
    border: 2px solid rgba(255,255,255,0.12);
    border-radius: 1rem;
    cursor: pointer;
    transition: all 0.2s;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
    min-height: 64px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1.3;
    font-family: inherit;
}

.category-card:active, .category-card.active {
    background: #8B5CF6;
    border-color: #8B5CF6;
    transform: scale(0.98);
}

/* ── Level section ────────────────────────────────────────────── */
.level-section {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border-radius: 1rem;
    padding: 1.25rem;
    border: 1px solid rgba(255,255,255,0.1);
}

.level-label {
    font-size: 1.1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    margin: 0 0 0.75rem;
    text-align: center;
}

.level-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 0.5rem;
}

.level-card {
    padding: 0.9rem 0.25rem;
    font-size: 1rem;
    font-weight: 700;
    background: rgba(139,92,246,0.2);
    color: #A78BFA;
    border: 2px solid rgba(139,92,246,0.3);
    border-radius: 0.75rem;
    cursor: pointer;
    transition: all 0.2s;
    font-family: inherit;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.level-card:active {
    background: #8B5CF6;
    color: #fff;
    transform: scale(0.96);
}

/* ── Practice screen ──────────────────────────────────────────── */
.practice-screen {
    background: linear-gradient(160deg, #0F172A 0%, #1E1B4B 100%);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    overflow: hidden;
}

/* ── Status bar ───────────────────────────────────────────────── */
.status-bar {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.75rem;
    padding: 1rem 1.5rem;
    transition: background 0.3s;
    flex-shrink: 0;
}

.status-icon { font-size: 1.4rem; }
.status-text { font-size: 1.2rem; font-weight: 700; }

.status-idle      { background: rgba(255,255,255,0.05); color: rgba(255,255,255,0.5); }
.status-loading   { background: rgba(255,255,255,0.08); color: rgba(255,255,255,0.6); }
.status-playing   { background: rgba(59,130,246,0.2);  color: #93C5FD; }
.status-listening { background: rgba(139,92,246,0.25); color: #C4B5FD; animation: pulse-bg 2s ease-in-out infinite; }
.status-processing{ background: rgba(245,158,11,0.2);  color: #FCD34D; }

@keyframes pulse-bg {
    0%, 100% { background: rgba(139,92,246,0.25); }
    50%       { background: rgba(139,92,246,0.4);  }
}

/* ── Word section ─────────────────────────────────────────────── */
.word-section {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 1.5rem;
    padding: 1rem 1.5rem;
    overflow: hidden;
}

.media-card {
    width: 100%;
    max-width: 320px;
    aspect-ratio: 1;
    border-radius: 1.25rem;
    overflow: hidden;
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    display: flex;
    align-items: center;
    justify-content: center;
}

.word-media {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.media-dual {
    display: flex;
    gap: 0.75rem;
    width: 100%;
    max-width: 320px;
}

.media-card-sm {
    flex: 1;
    max-width: none;
    aspect-ratio: 1;
}

.media-placeholder {
    font-size: 4rem;
    color: rgba(255,255,255,0.15);
}

.word-display {
    text-align: center;
}

.amharic-word {
    font-size: clamp(3.5rem, 12vw, 6rem);
    font-weight: 800;
    line-height: 1.1;
    font-family: 'Noto Sans Ethiopic', 'Ethiopic', sans-serif;
    background: linear-gradient(135deg, #fff 0%, #C4B5FD 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.spoken-display {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    background: rgba(255,255,255,0.07);
    border-radius: 0.75rem;
    padding: 0.75rem 1.25rem;
    font-size: 1.1rem;
}

.spoken-label { color: rgba(255,255,255,0.5); font-size: 1rem; }
.spoken-word  { color: #fff; font-weight: 600; }

/* ── Controls ─────────────────────────────────────────────────── */
.controls {
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 1rem 1.5rem max(1.5rem, env(safe-area-inset-bottom));
    flex-shrink: 0;
}

/* ── Feedback overlay ─────────────────────────────────────────── */
.feedback-overlay {
    position: fixed;
    inset: 0;
    z-index: 200;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(8px);
    pointer-events: none;
}

.feedback-success { background: rgba(16, 185, 129, 0.3); }
.feedback-error   { background: rgba(239, 68, 68, 0.3);  }

.feedback-content {
    text-align: center;
    animation: pop-in 0.3s cubic-bezier(0.34,1.56,0.64,1);
}

.feedback-emoji {
    font-size: 6rem;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.feedback-message {
    font-size: 2rem;
    font-weight: 800;
    color: #fff;
    text-shadow: 0 2px 12px rgba(0,0,0,0.4);
}

.feedback-spoken {
    margin-top: 0.75rem;
    font-size: 1.4rem;
    font-weight: 600;
    color: rgba(255,255,255,0.85);
    font-style: italic;
}

@keyframes pop-in {
    from { transform: scale(0.5); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

/* ── Transitions ──────────────────────────────────────────────── */
.slide-left-enter-active,
.slide-left-leave-active,
.slide-right-enter-active,
.slide-right-leave-active {
    transition: transform 0.3s ease, opacity 0.3s ease;
}

.slide-left-enter-from  { transform: translateX(100%); opacity: 0; }
.slide-left-leave-to    { transform: translateX(-100%); opacity: 0; }
.slide-right-enter-from { transform: translateX(-100%); opacity: 0; }
.slide-right-leave-to   { transform: translateX(100%); opacity: 0; }

.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to       { opacity: 0; }

/* ── Desktop / large screen ───────────────────────────────────── */
@media (min-width: 768px) {
    .settings-inner { padding: 4rem 2rem 5rem; }
    .app-title      { font-size: 3rem; }
    .category-grid  { grid-template-columns: repeat(3, 1fr); }
    .level-grid     { grid-template-columns: repeat(6, 1fr); }

    .practice-screen { flex-direction: row; }
    .word-section {
        flex-direction: row;
        padding: 2rem 3rem;
        justify-content: center;
        gap: 3rem;
    }
    .media-card { max-width: 380px; }
    .controls {
        flex-direction: column;
        max-width: 260px;
        justify-content: center;
        padding: 2rem;
    }
    .status-bar { padding: 1.25rem 2rem; }
}
</style>
