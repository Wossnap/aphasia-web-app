@extends('admin.layout')

@section('title', 'Speech Attempts Log')
@section('header', 'Speech Attempts Log')

@section('content')
    <!-- Filters -->
    <form method="GET" class="mb-4 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Status</label>
            <select name="status" class="block w-40 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                <option value="">All</option>
                <option value="correct" {{ $status === 'correct' ? 'selected' : '' }}>Correct</option>
                <option value="incorrect" {{ $status === 'incorrect' ? 'selected' : '' }}>Incorrect</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">From</label>
            <input type="date" name="from" value="{{ $from }}"
                   class="block border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">To</label>
            <input type="date" name="to" value="{{ $to }}"
                   class="block border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
                <i class="fas fa-filter mr-1.5"></i> Filter
            </button>
            @if($status || $from || $to)
                <a href="{{ route('admin.attempts.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700">
                    Clear
                </a>
            @endif
        </div>
    </form>

    <div class="mb-4 flex items-center justify-between gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
            <input type="checkbox" id="live-toggle" checked
                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Auto-refresh
        </label>
        <span class="flex items-center gap-2 text-xs text-gray-400">
            <span id="live-dot" class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
            <span id="live-status">Live — refreshing in 5s</span>
        </span>
    </div>

    {{--
        The bulk form holds no rows of its own — the checkboxes live down in
        the list and point back here with form="bulk-delete-form", so the list
        can keep being swapped out by the auto-refresh without a form element
        wrapping it.
    --}}
    <form method="POST" action="{{ route('admin.attempts.bulk-destroy') }}" id="bulk-delete-form">
        @csrf
        @method('DELETE')
    </form>

    <div class="mb-3 flex flex-wrap items-center gap-3 bg-white shadow rounded-lg px-4 py-3">
        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer select-none">
            <input type="checkbox" id="select-all"
                   class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
            Select all on this page
        </label>
        <button type="submit" form="bulk-delete-form" id="bulk-delete-btn" disabled
                class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md border border-red-300 bg-white hover:bg-red-50 text-red-700 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
            <i class="fas fa-trash-alt mr-1.5"></i> Delete selected
        </button>
        <span id="bulk-selected-count" class="text-sm text-gray-500">0 selected</span>
    </div>

    <div id="attempts-list">
    {{--
        One card list at every width, rather than a table for desktop and
        cards for mobile. The table needed eight columns, which meant
        horizontal scrolling on any real screen, and it duplicated every
        field's markup — the same attempt was written out twice in this file
        and the two copies had already drifted apart.

        The collapsed row carries what you scan by; the width that a desktop
        has spare goes into showing more of it inline rather than into more
        columns. Everything below md falls back to the stacked layout.
    --}}
    <div class="space-y-2">
        @forelse($attempts as $attempt)
            <details data-key="{{ $attempt->id }}"
                     class="bg-white shadow rounded-lg overflow-hidden border-l-4 {{ $attempt->is_correct ? 'border-green-400' : 'border-red-400' }}">
                <summary class="flex items-center gap-4 px-4 py-3 cursor-pointer select-none list-none hover:bg-gray-50">
                    {{-- Ticking a box would otherwise open the card, since any
                         click inside a summary toggles it. --}}
                    <label class="flex-none flex items-center" onclick="event.stopPropagation()">
                        <input type="checkbox" name="ids[]" value="{{ $attempt->id }}" form="bulk-delete-form"
                               class="js-attempt-check h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                               aria-label="Select this attempt">
                    </label>

                    {{-- Word: the thing you look for first, so it leads. --}}
                    <div class="min-w-0 flex-1 md:flex-none md:w-56">
                        <div class="font-bold text-gray-900 text-lg truncate">
                            {{ $attempt->word?->word ?? 'Deleted Word' }}
                        </div>
                        <div class="text-xs text-gray-500 truncate">
                            <span class="md:hidden">{{ $attempt->display_created_at->diffForHumans() }}@if($attempt->word?->meaning) &middot; {{ $attempt->word->meaning }}@endif</span>
                            <span class="hidden md:inline">{{ $attempt->word?->meaning ?? 'No meaning' }}</span>
                        </div>
                    </div>

                    {{-- What the speech API actually heard, against the word
                         above — the comparison the admin is here to make. --}}
                    <div class="hidden md:flex min-w-0 flex-1 items-center gap-2">
                        <i class="fas fa-arrow-right text-gray-300 text-xs flex-none"></i>
                        @if($attempt->transcription)
                            <span class="bg-gray-100 px-2 py-1 rounded text-gray-800 font-mono text-sm border border-gray-200 truncate">{{ $attempt->transcription }}</span>
                        @else
                            <span class="text-rose-500 italic text-xs">No result / Silenced</span>
                        @endif
                    </div>

                    <div class="hidden lg:block w-40 min-w-0">
                        @if($attempt->user)
                            <div class="text-sm text-gray-900 truncate">{{ $attempt->user->name }}</div>
                            <div class="text-xs text-gray-500 truncate">{{ $attempt->user->email }}</div>
                        @else
                            <span class="text-gray-400 italic text-xs">Anonymous/Guest</span>
                        @endif
                    </div>

                    <div class="hidden md:block w-32 text-right" title="{{ $attempt->display_created_at->format('D, M j, Y g:i:s A T') }}">
                        <div class="text-sm text-gray-900">{{ $attempt->display_created_at->format('M d, g:i A') }}</div>
                        <div class="text-xs text-gray-500">{{ $attempt->display_created_at->diffForHumans() }}</div>
                    </div>

                    <div class="flex items-center gap-2 flex-none">
                        @if($attempt->is_correct)
                            <span class="js-status-badge inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Correct</span>
                        @else
                            <span class="js-status-badge inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">Incorrect</span>
                        @endif
                        <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform details-chevron"></i>
                    </div>
                </summary>

                {{-- Expanded: the fields that don't earn a place in a row you
                     are scanning, laid out across the width instead of below
                     it once there's room. --}}
                <div class="border-t border-gray-100 px-4 py-4 grid gap-4 md:grid-cols-3 text-sm">
                    <div class="md:hidden">
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">User</div>
                        @if($attempt->user)
                            <div class="font-medium text-gray-900">{{ $attempt->user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $attempt->user->email }}</div>
                        @else
                            <span class="text-gray-400 italic">Anonymous/Guest</span>
                        @endif
                    </div>

                    <div class="md:hidden">
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">Speech API Result</div>
                        @if($attempt->transcription)
                            <span class="bg-gray-100 px-2 py-1 rounded text-gray-800 font-mono text-sm border border-gray-200">{{ $attempt->transcription }}</span>
                        @else
                            <span class="text-rose-500 italic text-xs">No result / Silenced</span>
                        @endif
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Checked Transliterations</div>
                        <div class="flex flex-wrap gap-1">
                            @if(is_array($attempt->checked_transliterations) && count($attempt->checked_transliterations))
                                @foreach($attempt->checked_transliterations as $translit)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">{{ $translit }}</span>
                                @endforeach
                            @else
                                <span class="text-gray-400 italic text-xs">—</span>
                            @endif
                        </div>
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Recording</div>
                        @if($attempt->audio_path)
                            <audio controls preload="none" class="h-8 w-full outline-none">
                                <source src="/audio/attempts/{{ $attempt->audio_path }}" type="audio/webm">
                            </audio>
                        @else
                            <span class="text-gray-400 italic text-xs">No recording</span>
                        @endif
                    </div>

                    <div>
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Actions</div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if(!$attempt->is_correct && $attempt->transcription && $attempt->word)
                                <form method="POST" action="{{ route('admin.attempts.add-transliteration', $attempt) }}" class="js-accept-form"
                                      data-confirm="Add &quot;{{ $attempt->transcription }}&quot; as a valid transliteration for &quot;{{ $attempt->word->word }}&quot;?">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center justify-center px-3 py-2 text-xs font-semibold rounded bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
                                            title="Add this API result as a valid pronunciation option">
                                        <i class="fas fa-plus mr-1"></i> Accept
                                    </button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.attempts.destroy', $attempt) }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit"
                                        class="inline-flex items-center justify-center px-3 py-2 text-xs font-semibold rounded border border-red-300 bg-white hover:bg-red-50 text-red-700 shadow-sm"
                                        onclick="return confirm('Are you sure you want to delete this attempt log entry?')">
                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                </button>
                            </form>
                            @if($attempt->word)
                                <a href="{{ route('admin.words.edit', $attempt->word) }}"
                                   class="inline-flex items-center justify-center px-3 py-2 text-xs font-semibold rounded border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 shadow-sm">
                                    <i class="fas fa-pen mr-1"></i> Edit word
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </details>
        @empty
            <div class="bg-white shadow rounded-lg px-6 py-12 text-center text-sm text-gray-500">
                <div class="text-4xl text-gray-300 mb-2"><i class="fas fa-microphone-slash"></i></div>
                <p>No speech attempts recorded yet.</p>
            </div>
        @endforelse

        @if($attempts->hasPages())
            <div class="pt-2">
                {{ $attempts->appends(request()->query())->links() }}
            </div>
        @endif
    </div>
    </div>{{-- /#attempts-list --}}


    <style>
        details > summary { list-style: none; }
        details > summary::-webkit-details-marker { display: none; }
        details[open] .details-chevron { transform: rotate(180deg); }
    </style>

    <script>
        (function () {
            const INTERVAL = 5; // seconds between silent refreshes
            const container = document.getElementById('attempts-list');
            const status = document.getElementById('live-status');
            const dot = document.getElementById('live-dot');
            const toggle = document.getElementById('live-toggle');
            const selectAll = document.getElementById('select-all');
            const countEl = document.getElementById('bulk-selected-count');
            const deleteBtn = document.getElementById('bulk-delete-btn');
            const bulkForm = document.getElementById('bulk-delete-form');
            const STORAGE_KEY = 'attempts-auto-refresh';
            let remaining = INTERVAL;
            let busy = false;

            // Remember the on/off preference across page loads.
            const saved = localStorage.getItem(STORAGE_KEY);
            if (saved !== null) toggle.checked = saved === '1';
            toggle.addEventListener('change', function () {
                localStorage.setItem(STORAGE_KEY, toggle.checked ? '1' : '0');
                remaining = INTERVAL;
                if (!toggle.checked) {
                    status.textContent = 'Auto-refresh off';
                    dot.className = 'h-2 w-2 rounded-full bg-gray-300';
                }
            });

            function anyAudioPlaying() {
                return Array.from(container.querySelectorAll('audio'))
                    .some(a => !a.paused && !a.ended);
            }

            // ── Bulk selection ────────────────────────────────────────────
            function checks() {
                return Array.from(container.querySelectorAll('.js-attempt-check'));
            }

            function selectedCount() {
                return checks().filter(c => c.checked).length;
            }

            function refreshBulk() {
                const all = checks();
                const n = all.filter(c => c.checked).length;
                countEl.textContent = n + ' selected';
                deleteBtn.disabled = n === 0;
                selectAll.checked = all.length > 0 && n === all.length;
                selectAll.indeterminate = n > 0 && n < all.length;
            }

            selectAll.addEventListener('change', function () {
                checks().forEach(c => { c.checked = selectAll.checked; });
                refreshBulk();
            });

            // The list is re-rendered on every refresh, so listen on the
            // container rather than on the checkboxes themselves.
            container.addEventListener('change', function (e) {
                if (e.target.classList.contains('js-attempt-check')) refreshBulk();
            });

            bulkForm.addEventListener('submit', function (e) {
                const n = selectedCount();
                if (n === 0) {
                    e.preventDefault();
                    return;
                }
                const ok = confirm('Delete ' + n + ' selected attempt' + (n === 1 ? '' : 's') +
                    '? The recordings go too, and this cannot be undone.');
                if (!ok) e.preventDefault();
            });

            refreshBulk();

            // Don't disrupt the admin: skip a silent swap while they have a card
            // open, are interacting with a control inside the list, or are part
            // way through picking rows to delete — a swap there would shift the
            // list under a selection they are still building.
            function userBusy() {
                if (anyAudioPlaying()) return true;
                if (selectedCount() > 0) return true;
                const active = document.activeElement;
                if (active && container.contains(active)) return true;
                return false;
            }

            async function refresh() {
                if (busy) return;
                busy = true;
                try {
                    const res = await fetch(window.location.href, {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    });
                    if (!res.ok) return;
                    const html = await res.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const fresh = doc.getElementById('attempts-list');
                    if (!fresh) return;

                    // Preserve which mobile cards are expanded across the swap.
                    const open = new Set(
                        Array.from(container.querySelectorAll('details[data-key]'))
                            .filter(d => d.open)
                            .map(d => d.getAttribute('data-key'))
                    );

                    // A selection pauses the refresh, so this should never have
                    // anything in it — carry it anyway so a swap can't silently
                    // drop rows the admin had ticked.
                    const ticked = new Set(
                        checks().filter(c => c.checked).map(c => c.value)
                    );

                    // Swap content in place — page scroll position is untouched.
                    container.innerHTML = fresh.innerHTML;

                    container.querySelectorAll('details[data-key]').forEach(d => {
                        if (open.has(d.getAttribute('data-key'))) d.open = true;
                    });

                    checks().forEach(c => { if (ticked.has(c.value)) c.checked = true; });
                    refreshBulk();
                } catch (_) {
                    /* ignore transient network errors; try again next tick */
                } finally {
                    busy = false;
                }
            }

            function tick() {
                if (!toggle.checked) {
                    status.textContent = 'Auto-refresh off';
                    dot.className = 'h-2 w-2 rounded-full bg-gray-300';
                    return;
                }
                if (userBusy()) {
                    remaining = INTERVAL;
                    status.textContent = 'Live — paused (in use)';
                    dot.className = 'h-2 w-2 rounded-full bg-amber-400';
                    return;
                }
                dot.className = 'h-2 w-2 rounded-full bg-green-500 animate-pulse';
                remaining -= 1;
                if (remaining <= 0) {
                    remaining = INTERVAL;
                    status.textContent = 'Refreshing…';
                    refresh().then(() => { status.textContent = 'Live — refreshing in ' + INTERVAL + 's'; });
                    return;
                }
                status.textContent = 'Live — refreshing in ' + remaining + 's';
            }

            setInterval(tick, 1000);

            // ── Accept without a full page reload ──────────────────────────
            // Submitting the "Accept" form normally reloads the whole page,
            // which loses the admin's scroll position. Intercept it, POST via
            // fetch, then update just that row/card in place.
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            function toast(message, ok) {
                const el = document.createElement('div');
                el.className = 'fixed bottom-4 right-4 z-50 px-4 py-2 rounded-md shadow-lg text-sm text-white ' +
                    (ok ? 'bg-green-600' : 'bg-red-600');
                el.textContent = message;
                document.body.appendChild(el);
                setTimeout(() => el.remove(), 3000);
            }

            function markCorrect(form) {
                const card = form.closest('details');
                if (card) {
                    // The card's own left edge is the status marker you scan
                    // by, so it has to move with the badge.
                    card.classList.remove('border-red-400');
                    card.classList.add('border-green-400');

                    card.querySelectorAll('.js-status-badge').forEach(badge => {
                        badge.classList.remove('bg-red-100', 'text-red-800', 'border-red-200');
                        badge.classList.add('bg-green-100', 'text-green-800', 'border-green-200');
                        badge.innerHTML = badge.innerHTML
                            .replace('text-red-400', 'text-green-400')
                            .replace('Incorrect', 'Correct');
                    });
                }
                form.remove();
            }

            container.addEventListener('submit', async function (e) {
                const form = e.target.closest('.js-accept-form');
                if (!form) return;
                e.preventDefault();

                const confirmMsg = form.getAttribute('data-confirm');
                if (confirmMsg && !confirm(confirmMsg)) return;

                const btn = form.querySelector('button[type="submit"]');
                if (btn) btn.disabled = true;

                try {
                    const res = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': csrf,
                            'Accept': 'application/json',
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (res.ok && data.ok) {
                        markCorrect(form);
                        toast(data.message || 'Accepted.', true);
                    } else {
                        if (btn) btn.disabled = false;
                        toast(data.message || 'Could not accept this attempt.', false);
                    }
                } catch (_) {
                    if (btn) btn.disabled = false;
                    toast('Network error — please try again.', false);
                }
            });
        })();
    </script>
@endsection
