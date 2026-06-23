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

    <div class="mb-4 flex items-center gap-2 text-xs text-gray-400">
        <span id="live-dot" class="h-2 w-2 rounded-full bg-green-500 animate-pulse"></span>
        <span id="live-status">Live — refreshing in 5s</span>
    </div>

    <div id="attempts-list">
    {{-- ─────────────── Desktop: table ─────────────── --}}
    <div class="hidden md:block bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amharic Word</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Speech API Result</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Checked Transliterations</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Audio Playback</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($attempts as $attempt)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" title="{{ $attempt->created_at }}">
                                {{ $attempt->created_at->format('M d, Y H:i') }}
                                <span class="block text-xs text-gray-500">{{ $attempt->created_at->diffForHumans() }}</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($attempt->user)
                                    <div class="font-medium text-gray-900">{{ $attempt->user->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $attempt->user->email }}</div>
                                @else
                                    <span class="text-gray-400 italic">Anonymous/Guest</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($attempt->word)
                                    <a href="{{ route('admin.words.edit', $attempt->word) }}"
                                       class="group inline-flex items-center gap-1.5" title="Edit this word">
                                        <span class="font-bold text-blue-700 group-hover:text-blue-900 group-hover:underline text-lg">{{ $attempt->word->word }}</span>
                                        <i class="fas fa-pen text-xs text-gray-400 group-hover:text-blue-600"></i>
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $attempt->word->meaning ?? 'No meaning' }}</div>
                                @else
                                    <span class="text-red-500 text-sm italic">Deleted Word</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                @if($attempt->transcription)
                                    <span class="bg-gray-100 px-2 py-1 rounded text-gray-800 font-mono text-sm border border-gray-200">{{ $attempt->transcription }}</span>
                                @else
                                    <span class="text-rose-500 italic text-xs">No result / Silenced</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <div class="flex flex-wrap gap-1 max-w-[250px]">
                                    @if(is_array($attempt->checked_transliterations))
                                        @foreach($attempt->checked_transliterations as $translit)
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-100">{{ $translit }}</span>
                                        @endforeach
                                    @else
                                        <span class="text-gray-400 italic text-xs">—</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($attempt->is_correct)
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200 shadow-sm">
                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                        Correct
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200 shadow-sm">
                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3" /></svg>
                                        Incorrect
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($attempt->audio_path)
                                    <audio controls class="h-8 max-w-[180px] outline-none">
                                        <source src="/audio/attempts/{{ $attempt->audio_path }}" type="audio/webm">
                                    </audio>
                                @else
                                    <span class="text-gray-400 italic text-xs">No recording</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <div class="flex justify-end items-center space-x-3">
                                    @if(!$attempt->is_correct && $attempt->transcription && $attempt->word)
                                        <form method="POST" action="{{ route('admin.attempts.add-transliteration', $attempt) }}" class="inline">
                                            @csrf
                                            <button type="submit"
                                                    class="inline-flex items-center px-2.5 py-1.5 border border-transparent text-xs font-semibold rounded bg-blue-600 hover:bg-blue-700 text-white shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
                                                    title="Add this API result as a valid pronunciation option"
                                                    onclick="return confirm('Add &quot;{{ $attempt->transcription }}&quot; as a valid transliteration for &quot;{{ $attempt->word->word }}&quot;?')">
                                                <i class="fas fa-plus mr-1"></i> Accept
                                            </button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.attempts.destroy', $attempt) }}" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center px-2.5 py-1.5 border border-red-300 text-xs font-semibold rounded bg-white hover:bg-red-50 text-red-700 shadow-sm transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                                                onclick="return confirm('Are you sure you want to delete this attempt log entry?')">
                                            <i class="fas fa-trash-alt mr-1"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 whitespace-nowrap text-sm text-gray-500 text-center font-medium">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="text-4xl text-gray-300"><i class="fas fa-microphone-slash"></i></div>
                                    <p>No speech attempts recorded yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($attempts->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $attempts->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    {{-- ─────────────── Mobile: collapsible cards ─────────────── --}}
    <div class="md:hidden space-y-3">
        @forelse($attempts as $attempt)
            <details data-key="{{ $attempt->id }}" class="bg-white shadow rounded-lg overflow-hidden">
                <summary class="flex items-center justify-between gap-3 px-4 py-3 cursor-pointer select-none list-none">
                    <div class="min-w-0">
                        <div class="font-bold text-gray-900 text-lg truncate">
                            {{ $attempt->word?->word ?? 'Deleted Word' }}
                        </div>
                        <div class="text-xs text-gray-500">{{ $attempt->created_at->diffForHumans() }}</div>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        @if($attempt->is_correct)
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Correct</span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">Incorrect</span>
                        @endif
                        <i class="fas fa-chevron-down text-gray-400 text-xs transition-transform details-chevron"></i>
                    </div>
                </summary>

                <div class="px-4 pb-4 pt-1 border-t border-gray-100 space-y-3 text-sm">
                    <div>
                        <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">User</div>
                        @if($attempt->user)
                            <div class="font-medium text-gray-900">{{ $attempt->user->name }}</div>
                            <div class="text-xs text-gray-500">{{ $attempt->user->email }}</div>
                        @else
                            <span class="text-gray-400 italic">Anonymous/Guest</span>
                        @endif
                    </div>

                    @if($attempt->word)
                        <div>
                            <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">Word</div>
                            <a href="{{ route('admin.words.edit', $attempt->word) }}" class="text-blue-700 font-semibold underline">
                                {{ $attempt->word->word }} <i class="fas fa-pen text-xs"></i>
                            </a>
                            <span class="text-xs text-gray-500">— {{ $attempt->word->meaning ?? 'No meaning' }}</span>
                        </div>
                    @endif

                    <div>
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

                    @if($attempt->audio_path)
                        <div>
                            <div class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-1">Recording</div>
                            <audio controls class="h-8 w-full outline-none">
                                <source src="/audio/attempts/{{ $attempt->audio_path }}" type="audio/webm">
                            </audio>
                        </div>
                    @endif

                    <div class="flex flex-wrap gap-2 pt-1">
                        @if(!$attempt->is_correct && $attempt->transcription && $attempt->word)
                            <form method="POST" action="{{ route('admin.attempts.add-transliteration', $attempt) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-semibold rounded bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
                                        onclick="return confirm('Add &quot;{{ $attempt->transcription }}&quot; as a valid transliteration for &quot;{{ $attempt->word->word }}&quot;?')">
                                    <i class="fas fa-plus mr-1"></i> Accept
                                </button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.attempts.destroy', $attempt) }}" class="flex-1">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                    class="w-full inline-flex items-center justify-center px-3 py-2 text-xs font-semibold rounded border border-red-300 bg-white hover:bg-red-50 text-red-700 shadow-sm"
                                    onclick="return confirm('Are you sure you want to delete this attempt log entry?')">
                                <i class="fas fa-trash-alt mr-1"></i> Delete
                            </button>
                        </form>
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
            let remaining = INTERVAL;
            let busy = false;

            function anyAudioPlaying() {
                return Array.from(container.querySelectorAll('audio'))
                    .some(a => !a.paused && !a.ended);
            }

            // Don't disrupt the admin: skip a silent swap while they have a card
            // open or are interacting with a control inside the list.
            function userBusy() {
                if (anyAudioPlaying()) return true;
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

                    // Swap content in place — page scroll position is untouched.
                    container.innerHTML = fresh.innerHTML;

                    container.querySelectorAll('details[data-key]').forEach(d => {
                        if (open.has(d.getAttribute('data-key'))) d.open = true;
                    });
                } catch (_) {
                    /* ignore transient network errors; try again next tick */
                } finally {
                    busy = false;
                }
            }

            function tick() {
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
        })();
    </script>
@endsection
