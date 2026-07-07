@extends('admin.layout')

@section('title', 'Word Progress')
@section('header', 'Word Progress')

@section('content')
    <!-- Filters -->
    <form method="GET" class="mb-4 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">User</label>
            @include('admin.partials.searchable-select', [
                'name' => 'user_id',
                'options' => $users->map(fn ($u) => ['id' => $u->id, 'label' => "{$u->name} ({$u->email})"]),
                'selected' => $userId,
                'placeholder' => 'Search users...',
                'emptyLabel' => 'All users',
            ])
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Word</label>
            @include('admin.partials.searchable-select', [
                'name' => 'word_id',
                'options' => $words->map(fn ($w) => ['id' => $w->id, 'label' => $w->word]),
                'selected' => $wordId,
                'placeholder' => 'Search words...',
                'emptyLabel' => 'All words',
            ])
        </div>
        <div class="flex items-center gap-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
                <i class="fas fa-filter mr-1.5"></i> Filter
            </button>
            @if($userId || $wordId)
                <a href="{{ route('admin.word-progress.index') }}"
                   class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700">
                    Clear
                </a>
            @endif
        </div>
    </form>

    @if($stats['mastered'] + $stats['improving'] + $stats['needs_practice'] > 0)
        <!-- Stat tiles -->
        <div class="grid grid-cols-3 gap-3 mb-4">
            <div class="bg-white shadow rounded-lg p-4 border-t-4 border-rose-400">
                <div class="text-2xl font-bold text-rose-700">{{ $stats['needs_practice'] }}</div>
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Needs Practice</div>
            </div>
            <div class="bg-white shadow rounded-lg p-4 border-t-4 border-amber-400">
                <div class="text-2xl font-bold text-amber-700">{{ $stats['improving'] }}</div>
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Improving</div>
            </div>
            <div class="bg-white shadow rounded-lg p-4 border-t-4 border-green-400">
                <div class="text-2xl font-bold text-green-700">{{ $stats['mastered'] }}</div>
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Mastered</div>
            </div>
        </div>

        <!-- Categories: focus on / doing great -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-rose-50 border border-rose-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-rose-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-bullseye"></i> Categories to focus on
                </h2>
                @forelse($focusCategories as $cat)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-rose-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900">{{ $cat['category']->name }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $cat['word_count'] }} word{{ $cat['word_count'] === 1 ? '' : 's' }} tried</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-rose-700">{{ $cat['avg_score'] }}/10</div>
                            <div class="text-xs text-gray-500">avg score</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No struggling categories right now.</p>
                @endforelse
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-green-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-star"></i> Categories doing great
                </h2>
                @forelse($greatCategories as $cat)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-green-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900">{{ $cat['category']->name }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $cat['word_count'] }} word{{ $cat['word_count'] === 1 ? '' : 's' }} tried</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-green-700">{{ $cat['avg_score'] }}/10</div>
                            <div class="text-xs text-gray-500">avg score</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No categories doing great yet.</p>
                @endforelse
            </div>
        </div>

        <!-- Levels: focus on / doing great (e.g. the ሀ-family vs the ለ-family within Fidel) -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div class="bg-rose-50 border border-rose-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-rose-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-bullseye"></i> Levels to focus on
                </h2>
                @forelse($focusLevels as $lvl)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-rose-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900 text-lg">{{ $lvl['label'] ?? ('Level ' . $lvl['level']) }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $lvl['category']->name }} &middot; {{ $lvl['word_count'] }} word{{ $lvl['word_count'] === 1 ? '' : 's' }} tried</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-rose-700">{{ $lvl['avg_score'] }}/10</div>
                            <div class="text-xs text-gray-500">avg score</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No struggling levels right now.</p>
                @endforelse
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-green-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-star"></i> Levels doing great
                </h2>
                @forelse($greatLevels as $lvl)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-green-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900 text-lg">{{ $lvl['label'] ?? ('Level ' . $lvl['level']) }}</span>
                            <span class="text-xs text-gray-500 ml-1">{{ $lvl['category']->name }} &middot; {{ $lvl['word_count'] }} word{{ $lvl['word_count'] === 1 ? '' : 's' }} tried</span>
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-green-700">{{ $lvl['avg_score'] }}/10</div>
                            <div class="text-xs text-gray-500">avg score</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No levels doing great yet.</p>
                @endforelse
            </div>
        </div>

        <!-- Words: focus on next / doing great -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-rose-50 border border-rose-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-rose-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-bullseye"></i> Words to focus on next
                </h2>
                @forelse($focusWords as $row)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-rose-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900 text-lg">{{ $row['word']->word ?? '—' }}</span>
                            @if(!$userId)
                                <span class="text-xs text-gray-500 ml-1">{{ $row['user']->name ?? '' }}</span>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-rose-700">{{ $row['score'] }}/10</div>
                            <div class="text-xs text-gray-500">{{ $row['accuracy'] }}% accuracy</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">Nothing struggling right now — nice work.</p>
                @endforelse
            </div>

            <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                <h2 class="text-sm font-bold text-green-800 uppercase tracking-wider mb-3 flex items-center gap-2">
                    <i class="fas fa-star"></i> Words doing great
                </h2>
                @forelse($masteredWords as $row)
                    <div class="flex items-center justify-between gap-3 py-2 {{ !$loop->last ? 'border-b border-green-100' : '' }}">
                        <div class="min-w-0">
                            <span class="font-bold text-gray-900 text-lg">{{ $row['word']->word ?? '—' }}</span>
                            @if(!$userId)
                                <span class="text-xs text-gray-500 ml-1">{{ $row['user']->name ?? '' }}</span>
                            @endif
                        </div>
                        <div class="text-right flex-shrink-0">
                            <div class="font-bold text-green-700">{{ $row['score'] }}/10</div>
                            <div class="text-xs text-gray-500">{{ $row['accuracy'] }}% accuracy</div>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No words mastered yet.</p>
                @endforelse
            </div>
        </div>
    @endif

    <p class="text-sm text-gray-500 mb-4">
        Full list below, sorted weakest-first. A word only appears once a user has attempted it — mastery follows
        the standard speech-therapy criterion of <strong>80%+ accuracy across the last 2 practice sessions</strong>.
    </p>

    <!-- Desktop: table -->
    <div class="hidden md:block bg-white shadow rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amharic Word</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Score</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recent Accuracy</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Trend</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Attempt</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    @forelse($progress as $row)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($row['user'])
                                    <div class="font-medium text-gray-900">{{ $row['user']->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $row['user']->email }}</div>
                                @else
                                    <span class="text-gray-400 italic">Deleted user</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                @if($row['word'])
                                    <a href="{{ route('admin.words.edit', $row['word']) }}"
                                       class="group inline-flex items-center gap-1.5" title="Edit this word">
                                        <span class="font-bold text-blue-700 group-hover:text-blue-900 group-hover:underline text-lg">{{ $row['word']->word }}</span>
                                        <i class="fas fa-pen text-xs text-gray-400 group-hover:text-blue-600"></i>
                                    </a>
                                    <div class="text-xs text-gray-500">{{ $row['word']->meaning ?? 'No meaning' }}</div>
                                @else
                                    <span class="text-red-500 text-sm italic">Deleted Word</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-gray-900">
                                {{ $row['score'] }}/10
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($row['status'] === 'mastered')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Mastered</span>
                                @elseif($row['status'] === 'improving')
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">Improving</span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">Needs Practice</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $row['accuracy'] }}% <span class="text-xs text-gray-500">({{ $row['correct'] }}/{{ $row['attempts'] }})</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($row['trend'] === 'up')
                                    <i class="fas fa-arrow-trend-up text-green-600"></i>
                                @elseif($row['trend'] === 'down')
                                    <i class="fas fa-arrow-trend-down text-rose-600"></i>
                                @elseif($row['trend'] === 'flat')
                                    <i class="fas fa-arrow-right text-gray-400"></i>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" title="{{ $row['last_attempt_at'] }}">
                                {{ $row['last_attempt_at']->diffForHumans() }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-12 whitespace-nowrap text-sm text-gray-500 text-center font-medium">
                                <div class="flex flex-col items-center justify-center space-y-2">
                                    <div class="text-4xl text-gray-300"><i class="fas fa-chart-line"></i></div>
                                    <p>No word attempts recorded yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($progress->hasPages())
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                {{ $progress->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    <!-- Mobile: cards -->
    <div class="md:hidden space-y-3">
        @forelse($progress as $row)
            <div class="bg-white shadow rounded-lg px-4 py-3">
                <div class="flex items-center justify-between gap-3 mb-2">
                    <div class="min-w-0">
                        <div class="font-bold text-gray-900 text-lg truncate">{{ $row['word']->word ?? 'Deleted Word' }}</div>
                        <div class="text-xs text-gray-500">
                            {{ $row['user']->name ?? 'Deleted user' }} &middot; {{ $row['last_attempt_at']->diffForHumans() }}
                        </div>
                    </div>
                    <div class="text-lg font-bold text-gray-900 flex-shrink-0">{{ $row['score'] }}/10</div>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    @if($row['status'] === 'mastered')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">Mastered</span>
                    @elseif($row['status'] === 'improving')
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">Improving</span>
                    @else
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-rose-100 text-rose-800 border border-rose-200">Needs Practice</span>
                    @endif
                    <span class="text-xs text-gray-500">{{ $row['accuracy'] }}% ({{ $row['correct'] }}/{{ $row['attempts'] }})</span>
                </div>
            </div>
        @empty
            <div class="bg-white shadow rounded-lg px-6 py-12 text-center text-sm text-gray-500">
                <div class="text-4xl text-gray-300 mb-2"><i class="fas fa-chart-line"></i></div>
                <p>No word attempts recorded yet.</p>
            </div>
        @endforelse

        @if($progress->hasPages())
            <div class="pt-2">
                {{ $progress->appends(request()->query())->links() }}
            </div>
        @endif
    </div>

    <script>
        // Dependency-free searchable combobox: type to filter, click to pick.
        // Backs a hidden input so the surrounding <form> submits a plain id.
        document.querySelectorAll('.searchable-select').forEach(function (wrapper) {
            const input  = wrapper.querySelector('[data-search-input]');
            const hidden = wrapper.querySelector('[data-hidden-input]');
            const list   = wrapper.querySelector('[data-options-list]');
            const options = Array.from(list.children);

            function showList() { list.classList.remove('hidden'); }
            function hideList() { list.classList.add('hidden'); }

            function filterOptions() {
                const term = input.value.trim().toLowerCase();
                options.forEach(function (opt) {
                    const isEmptyOption = opt.dataset.value === '';
                    const label = (opt.dataset.label || '').toLowerCase();
                    opt.classList.toggle('hidden', !isEmptyOption && !label.includes(term));
                });
            }

            input.addEventListener('focus', function () { filterOptions(); showList(); });
            input.addEventListener('input', function () { filterOptions(); showList(); });
            input.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { hideList(); input.blur(); }
            });

            // Revert to the last confirmed selection if the field is left
            // without picking an option from the filtered list.
            input.addEventListener('blur', function () {
                setTimeout(function () {
                    const match = options.find(o => o.dataset.value === hidden.value);
                    input.value = match ? (match.dataset.label || '') : '';
                    hideList();
                }, 150);
            });

            options.forEach(function (opt) {
                opt.addEventListener('click', function () {
                    hidden.value = opt.dataset.value;
                    input.value = opt.dataset.label || '';
                    hideList();
                });
            });

            document.addEventListener('click', function (e) {
                if (!wrapper.contains(e.target)) hideList();
            });
        });
    </script>
@endsection
