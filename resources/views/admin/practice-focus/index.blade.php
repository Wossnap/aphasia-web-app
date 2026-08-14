@extends('admin.layout')

@section('title', 'Work On These With Him')
@section('header', 'Work On These With Him')

@section('content')
    <p class="mb-4 text-sm text-gray-600 max-w-3xl">
        Items he is getting nowhere with alone — below {{ round($needsHelpBelow * 100) }}% and not
        improving. He still meets them in practice; nothing here is withheld from him. These are
        simply where sitting with him for a session or two is worth more than another solo
        attempt. The recogniser's actual output is shown beside each one, because it is usually
        obvious at a glance whether the trouble is his speech or the machine's ear.
    </p>

    <form method="GET" class="mb-6 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Category</label>
            <select name="category_id" class="block w-64 border-gray-300 rounded-md shadow-sm text-sm">
                @foreach($categories as $c)
                    <option value="{{ $c->id }}" {{ $category && $category->id === $c->id ? 'selected' : '' }}>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Who</label>
            <select name="user_id" class="block w-48 border-gray-300 rounded-md shadow-sm text-sm">
                @foreach($users as $u)
                    <option value="{{ $u->id }}" {{ $userId === $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
            <i class="fas fa-filter mr-1.5"></i> Show
        </button>
    </form>

    @if($familyRows->isNotEmpty())
        {{--
            Grouped first, because the family is the unit that is actually
            stuck. Seven letters of one consonant is one thing to sit down and
            work through, not seven separate problems.
        --}}
        <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">Stuck families</h2>
        <div class="mb-6 flex flex-wrap gap-3">
            @foreach($familyRows as $family)
                <div class="bg-white shadow rounded-lg px-4 py-3 border-l-4 border-amber-400">
                    <div class="text-2xl font-semibold" style="font-family: 'Noto Sans Ethiopic', sans-serif;">
                        {{ implode(' ', $family['letters']) }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ $family['count'] }} {{ Str::plural('letter', $family['count']) }} · avg {{ $family['accuracy'] }}%
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-2">
        Items needing you ({{ $rows->count() }})
    </h2>

    @forelse($rows as $row)
        <div class="bg-white shadow rounded-lg mb-3 p-4 border-l-4 border-red-400">
            <div class="flex flex-wrap items-start gap-4">
                <div class="text-4xl font-semibold leading-none"
                     style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $row['word'] }}</div>

                <div class="flex-1 min-w-[14rem]">
                    <div class="text-sm text-gray-700">
                        <span class="font-semibold">{{ round($row['accuracy'] * 100) }}%</span>
                        over {{ $row['attempts'] }} {{ Str::plural('try', $row['attempts']) }}
                        @if($row['miss_streak'] > 1)
                            · <span class="text-red-600 font-medium">{{ $row['miss_streak'] }} misses in a row</span>
                        @endif
                    </div>

                    @if($row['heard'])
                        <div class="mt-2 text-sm text-gray-600">
                            <span class="text-xs uppercase tracking-wider text-gray-400">Recogniser heard</span>
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach($row['heard'] as $heard)
                                    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded bg-gray-100 text-gray-700"
                                          style="font-family: 'Noto Sans Ethiopic', sans-serif;">
                                        {{ $heard['text'] }}
                                        <span class="text-xs text-gray-400">×{{ $heard['times'] }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($row['confused_with'])
                        <div class="mt-2 text-xs text-gray-500">
                            Gets confused with
                            <span style="font-family: 'Noto Sans Ethiopic', sans-serif;">
                                {{ implode(' ', $row['confused_with']) }}
                            </span>
                            — the engine keeps these apart in a session.
                        </div>
                    @endif
                </div>

                <a href="{{ route('admin.attempts.index', ['status' => 'incorrect']) }}"
                   class="text-sm text-blue-600 hover:text-blue-800 whitespace-nowrap">
                    Listen to attempts <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500">
            Nothing in this category needs you right now — everything he has tried is above
            {{ round($needsHelpBelow * 100) }}%.
        </div>
    @endforelse
@endsection
