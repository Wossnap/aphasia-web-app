<template>
    <div class="app">
        <!-- ── Settings screen ── -->
        <transition name="slide-left">
            <div v-if="screen === 'settings'" class="screen settings-screen" key="settings">
                <div class="settings-inner">
                    <button class="logout-btn" @click="handleLogout">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </button>
                    <h1 class="app-title">Amharic Practice</h1>
                    <p class="app-subtitle">Hear a word. Say it back.</p>

                    <!-- Mode toggle -->
                    <div class="mode-toggle">
                        <button
                            class="mode-btn"
                            :class="{ 'mode-btn-active': practiceMode === 'consecutive' }"
                            @click="practiceMode = 'consecutive'"
                        >
                            <i class="fas fa-list-ol"></i> In Order
                        </button>
                        <button
                            class="mode-btn"
                            :class="{ 'mode-btn-active': practiceMode === 'random' }"
                            @click="practiceMode = 'random'"
                        >
                            <i class="fas fa-random"></i> Shuffle
                        </button>
                    </div>

                    <!-- Start Practice (random) commented out — users pick a topic below
                    <button class="btn btn-primary btn-xl" @click="startRandom">
                        <i class="fas fa-play btn-icon"></i>
                        Start Practice
                    </button>
                    -->

                    <div class="divider"><span>choose a topic</span></div>

                    <div class="selector-container">
                        <transition :name="selectorTransition" mode="out-in">
                            <!-- Category grid -->
                            <div v-if="settingsView === 'categories'" key="categories" class="category-grid">
                                <button
                                    v-for="cat in categories"
                                    :key="cat.id"
                                    class="category-card"
                                    @click="selectCategory(cat)"
                                >
                                    {{ cat.name }}
                                </button>
                            </div>

                            <!-- Level selector -->
                            <div v-else-if="settingsView === 'levels'" key="levels" class="level-section">
                                <div class="level-section-header">
                                    <button class="btn-back" @click="backToCategories">
                                        <i class="fas fa-arrow-left"></i>
                                    </button>
                                    <p class="level-label">{{ selectedCategory?.name }} — Choose a level</p>
                                </div>
                                <div v-if="levelsLoading" class="levels-loading">
                                    <i class="fas fa-spinner fa-spin"></i>
                                </div>
                                <div v-else class="level-grid">
                                    <button
                                        v-for="lvl in levels"
                                        :key="lvl.level"
                                        class="level-card"
                                        :class="{ 'level-card-letter': lvl.label }"
                                        @click="startLevel(lvl.level)"
                                    >
                                        {{ lvl.label ?? ('Level ' + lvl.level) }}
                                    </button>
                                </div>
                            </div>
                        </transition>
                    </div>

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

                    <!-- Only show what was said in the body when it was WRONG -->
                    <div v-if="spokenWord && feedback === 'error'" class="spoken-display">
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

                    <div v-if="prevLevel || nextLevel" class="level-nav">
                        <button
                            v-if="prevLevel"
                            class="btn btn-accent btn-lg"
                            :disabled="speechState === 'loading'"
                            @click="goToPrevLevel"
                        >
                            <i class="fas fa-arrow-left btn-icon"></i>
                            Prev Level
                        </button>

                        <button
                            v-if="nextLevel"
                            class="btn btn-accent btn-lg"
                            :disabled="speechState === 'loading'"
                            @click="goToNextLevel"
                        >
                            <i class="fas fa-arrow-right btn-icon"></i>
                            Next Level
                        </button>
                    </div>

                    <button class="btn btn-danger btn-lg" @click="stopPractice">
                        <i class="fas fa-stop btn-icon"></i>
                        Stop
                    </button>
                </div>

                <!-- Floating pause / resume button -->
                <button
                    v-if="!awaitingStart"
                    class="btn-pause-float"
                    :class="{ 'btn-pause-float--paused': paused }"
                    @click="togglePause"
                    :title="paused ? 'Resume' : 'Pause'"
                >
                    <i :class="paused ? 'fas fa-play' : 'fas fa-pause'"></i>
                </button>

                <!-- Tap-to-start overlay (deep link / reload onto a practice URL) -->
                <transition name="fade">
                    <div v-if="awaitingStart" class="start-overlay" @click="beginPractice">
                        <button class="btn btn-primary btn-xl">
                            <i class="fas fa-play btn-icon"></i>
                            Tap to start
                        </button>
                    </div>
                </transition>
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
                    <!-- On success: show what they said under Excellent -->
                    <div v-if="feedback === 'success' && spokenWord" class="feedback-spoken feedback-spoken-success">
                        "{{ spokenWord }}"
                    </div>
                    <!-- On error: show what they said in the overlay too -->
                    <div v-if="feedback === 'error' && spokenWord" class="feedback-spoken">
                        "{{ spokenWord }}"
                    </div>
                </div>
            </div>
        </transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSpeech } from '../composables/useSpeech';

const props = defineProps({
    categories:        { type: Array,  default: () => [] },
    speechDriver:      { type: String, default: 'browser' },
    speechVersion:     { type: String, default: 'v1' },
    initialSlug:       { type: String, default: null },
    initialCategoryId: { type: Number, default: null },
    initialLevel:      { type: Number, default: null },
    translations:      { type: Object, default: () => ({}) },
});

// ─── App state ────────────────────────────────────────────────────────────────
const screen          = ref('settings');
const speechState     = ref('idle');   // idle | loading | playing | listening | processing
const currentWord     = ref(null);
const spokenWord      = ref('');
const feedback        = ref(null);     // null | 'success' | 'error'
const selectedCategory = ref(null);
const levels          = ref([]);
const levelsLoading   = ref(false);
const currentCategory = ref(null);
const currentLevel    = ref(null);
const practiceMode    = ref('consecutive');
const mediaUrl        = ref(null);
const mediaUrl2       = ref(null); // second visual: image when gif+image both exist
const awaitingStart   = ref(false); // deep-link landed on a practice URL; wait for a tap (audio needs a gesture)
const paused          = ref(false); // user paused mid-session (no audio, no mic)

// 'categories' | 'levels'
const settingsView    = ref('categories');
const selectorTransition = ref('slide-selector-forward');

// PWA install
const deferredInstall = ref(null);
const showInstall     = ref(false);

// ─── Speech composable ────────────────────────────────────────────────────────
const { playWordAndListen, replayWord, stopAll } = useSpeech({
    speechDriver:  props.speechDriver,
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

// ─── URL sync (History API) ───────────────────────────────────────────────────
// Forward navigation updates the URL without an Inertia visit, so the click
// gesture is preserved (audio/mic autoplay needs it). We keep the existing
// history.state (Inertia's page lives there) so this component stays mounted.
function syncUrl(path) {
    if (window.location.pathname === path) return;
    window.history.pushState(window.history.state, '', path);
}

// ─── Category / level selection ───────────────────────────────────────────────
async function fetchLevels(cat) {
    levels.value = [];
    levelsLoading.value = true;
    try {
        const res = await fetch(`/api/categories/${cat.id}/levels`);
        levels.value = await res.json();
    } catch (_) {}
    levelsLoading.value = false;
}

async function selectCategory(cat) {
    selectedCategory.value = cat;
    selectorTransition.value = 'slide-selector-forward';
    settingsView.value = 'levels';
    syncUrl(`/${cat.slug}`);
    await fetchLevels(cat);
}

function backToCategories() {
    selectorTransition.value = 'slide-selector-back';
    settingsView.value = 'categories';
    selectedCategory.value = null;
    levels.value = [];
    syncUrl('/');
}

function startRandom() {
    currentCategory.value = null;
    currentLevel.value    = null;
    launchPractice();
}

function startLevel(lvl) {
    currentCategory.value = selectedCategory.value?.id ?? null;
    currentLevel.value    = lvl;
    if (selectedCategory.value?.slug) {
        syncUrl(`/${selectedCategory.value.slug}/level-${lvl}`);
    }
    launchPractice();
}

async function launchPractice() {
    awaitingStart.value = false;
    paused.value = false;
    screen.value = 'practice';
    speechState.value = 'loading';
    await loadWord();
    await playWordAndListen(currentWord.value);
}

// Tap-to-start: used when a practice URL is opened cold (reload / deep link),
// where there is no user gesture yet to allow audio + mic.
async function beginPractice() {
    awaitingStart.value = false;
    speechState.value = 'loading';
    if (!currentWord.value) await loadWord();
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

// Pause: stop all audio/mic immediately; resume replays the current word.
function togglePause() {
    if (paused.value) {
        paused.value = false;
        playWordAndListen(currentWord.value);
    } else {
        paused.value = true;
        stopAll();
        speechState.value = 'idle';
    }
}

// Adjacent levels — only shown when practising a specific level that has a
// predecessor / successor in the ordered level list.
const nextLevel = computed(() => {
    if (!currentLevel.value || !levels.value.length) return null;
    const idx = levels.value.findIndex(l => l.level === currentLevel.value);
    return idx !== -1 && idx < levels.value.length - 1 ? levels.value[idx + 1] : null;
});

const prevLevel = computed(() => {
    if (!currentLevel.value || !levels.value.length) return null;
    const idx = levels.value.findIndex(l => l.level === currentLevel.value);
    return idx > 0 ? levels.value[idx - 1] : null;
});

async function goToLevel(target) {
    if (!target) return;
    currentLevel.value = target.level;
    if (selectedCategory.value?.slug) {
        syncUrl(`/${selectedCategory.value.slug}/level-${target.level}`);
    }
    await launchPractice();
}

const goToNextLevel = () => goToLevel(nextLevel.value);
const goToPrevLevel = () => goToLevel(prevLevel.value);

// Stop returns to the level grid of the current category (or to the category
// list when practicing the random pool, which has no addressable location).
function stopPractice() {
    stopAll();
    currentWord.value = null;
    spokenWord.value  = '';
    feedback.value    = null;
    speechState.value = 'idle';
    awaitingStart.value = false;
    paused.value = false;
    screen.value = 'settings';

    if (selectedCategory.value?.slug) {
        settingsView.value = 'levels';
        syncUrl(`/${selectedCategory.value.slug}`);
        if (!levels.value.length) fetchLevels(selectedCategory.value);
    } else {
        settingsView.value = 'categories';
        selectedCategory.value = null;
        syncUrl('/');
    }
}

// ─── Deep-link / history wiring ───────────────────────────────────────────────
// Map a URL path onto UI state. Used on mount (from server props) and on
// browser back/forward (popstate). Never auto-plays audio — a practice URL
// shows the word and waits for a tap.
function applyPath(pathname) {
    const parts = pathname.replace(/^\/+|\/+$/g, '').split('/').filter(Boolean);

    // Categories
    if (parts.length === 0) {
        stopAll();
        screen.value = 'settings';
        settingsView.value = 'categories';
        selectedCategory.value = null;
        awaitingStart.value = false;
        speechState.value = 'idle';
        return;
    }

    const cat = props.categories.find(c => c.slug === parts[0]);
    if (!cat) { // unknown slug -> categories
        screen.value = 'settings';
        settingsView.value = 'categories';
        selectedCategory.value = null;
        return;
    }
    selectedCategory.value = cat;

    const levelMatch = parts[1] ? parts[1].match(/^level-(\d+)$/) : null;
    if (levelMatch) {
        // Practice URL: show the word, wait for a tap to start (no gesture here).
        stopAll();
        currentCategory.value = cat.id;
        currentLevel.value    = parseInt(levelMatch[1], 10);
        screen.value = 'practice';
        awaitingStart.value = true;
        spokenWord.value = '';
        loadWord();
    } else {
        // Levels view.
        stopAll();
        screen.value = 'settings';
        settingsView.value = 'levels';
        awaitingStart.value = false;
        speechState.value = 'idle';
        fetchLevels(cat);
    }
}

function onPopState() {
    applyPath(window.location.pathname);
}

// ─── Speech result handling ───────────────────────────────────────────────────
async function handleSpokenResult(spoken, serverVerdict = null) {
    spokenWord.value  = spoken;
    speechState.value = 'processing';

    // Keep the in-memory word in sync with the DB so a freshly accepted
    // transliteration is reflected immediately for the rest of this session.
    if (serverVerdict?.transliterations && currentWord.value) {
        currentWord.value.transliterations = serverVerdict.transliterations;
    }

    // Trust the server's verdict (checked against the live DB) when present;
    // fall back to a local match for the browser-speech path.
    const isCorrect = typeof serverVerdict?.isCorrect === 'boolean'
        ? serverVerdict.isCorrect
        : (currentWord.value?.transliterations?.some(t =>
              spoken.toLowerCase().includes(t.toLowerCase())
          ) ?? false);

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
    // Mic is intentionally NOT opened here — it would light up the mic indicator
    // on the landing screen. It opens when practice starts (playWordAndListen).

    // Deep link: open straight onto the category's levels, or a practice URL
    // (which shows the word and waits for a tap, since there's no gesture yet).
    if (props.initialSlug) {
        let path = `/${props.initialSlug}`;
        if (props.initialLevel != null) path += `/level-${props.initialLevel}`;
        applyPath(path);
    }

    window.addEventListener('popstate', onPopState);

    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredInstall.value = e;
        showInstall.value     = true;
    });

    if ('speechSynthesis' in window) window.speechSynthesis.getVoices();
});

onUnmounted(() => {
    window.removeEventListener('popstate', onPopState);
});

function handleLogout() {
    router.post('/logout');
}

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
    position: relative;
}

.logout-btn {
    position: absolute;
    top: 1rem;
    right: 1.5rem;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.6);
    padding: 0.4rem 0.8rem;
    border-radius: 0.5rem;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.35rem;
}

.logout-btn:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
    color: #f87171;
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

/* ── Selector container ───────────────────────────────────────── */
.selector-container {
    width: 100%;
    overflow: hidden;
}

/* ── Level section ────────────────────────────────────────────── */
.level-section {
    width: 100%;
    background: rgba(255,255,255,0.05);
    border-radius: 1rem;
    padding: 1.25rem;
    border: 1px solid rgba(255,255,255,0.1);
}

.level-section-header {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 0.75rem;
}

.btn-back {
    background: rgba(255,255,255,0.1);
    border: 2px solid rgba(255,255,255,0.2);
    border-radius: 0.6rem;
    color: #fff;
    padding: 0.45rem 0.75rem;
    font-size: 1rem;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.2s;
    touch-action: manipulation;
    -webkit-tap-highlight-color: transparent;
}

.btn-back:active { background: rgba(255,255,255,0.2); }

.level-label {
    font-size: 1rem;
    font-weight: 600;
    color: rgba(255,255,255,0.7);
    margin: 0;
    text-align: left;
    flex: 1;
}

.levels-loading {
    text-align: center;
    padding: 1.5rem;
    font-size: 1.5rem;
    color: rgba(255,255,255,0.4);
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

.level-card-letter {
    font-size: 1.8rem;
    line-height: 1.2;
}

.btn-pause-float {
    position: absolute;
    top: 1rem;
    right: 1rem;
    width: 2.75rem;
    height: 2.75rem;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
    border: 1.5px solid rgba(255, 255, 255, 0.2);
    color: rgba(255, 255, 255, 0.7);
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    z-index: 10;
    transition: background 0.2s, color 0.2s;
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

.btn-pause-float:active,
.btn-pause-float--paused {
    background: rgba(139, 92, 246, 0.4);
    border-color: rgba(139, 92, 246, 0.6);
    color: #fff;
}

.start-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(4px);
    z-index: 20;
    cursor: pointer;
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

/* Prev / Next level sit side by side, splitting the row evenly. */
.level-nav {
    display: flex;
    gap: 0.75rem;
}

.level-nav .btn {
    flex: 1;
    min-width: 0;
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

.feedback-spoken-success {
    font-size: 1.6rem;
    color: #fff;
    opacity: 0.9;
}

.btn-accent {
    background: rgba(20, 184, 166, 0.25);
    color: #5EEAD4;
    border: 2px solid rgba(20, 184, 166, 0.4);
}

@keyframes pop-in {
    from { transform: scale(0.5); opacity: 0; }
    to   { transform: scale(1);   opacity: 1; }
}

/* ── Selector slide transitions ─────────────────────────────── */
.slide-selector-forward-enter-active,
.slide-selector-forward-leave-active,
.slide-selector-back-enter-active,
.slide-selector-back-leave-active {
    transition: transform 0.28s ease, opacity 0.28s ease;
    position: relative;
}

.slide-selector-forward-enter-from { transform: translateX(60px); opacity: 0; }
.slide-selector-forward-leave-to   { transform: translateX(-60px); opacity: 0; }

.slide-selector-back-enter-from    { transform: translateX(-60px); opacity: 0; }
.slide-selector-back-leave-to      { transform: translateX(60px);  opacity: 0; }

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
