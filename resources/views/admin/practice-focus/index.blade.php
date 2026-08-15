@extends('admin.layout')

@section('title', 'Work On These With Him')
@section('header', 'Work On These With Him')

@section('content')
    {{--
        One card per family, not per letter. A single letter is not the unit of
        the work: get the first of a row and the rest of it follows, so what
        belongs on screen is the family, the letter to start from, and whether
        that letter is the one going wrong.
    --}}
    <p class="mb-4 text-sm text-gray-600 max-w-3xl">
        Families he is getting nowhere with alone. Start on the first letter — the rest of the row
        usually follows once that one lands. Nothing here is withheld from him; these are simply
        where sitting together beats another solo attempt.
    </p>

    <form method="GET" class="mb-6 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Category</label>
            <select name="category_id" class="block w-64 border-gray-300 rounded-md shadow-sm text-sm">
                @foreach($categories->sortByDesc(fn ($c) => $needingHelp[$c->id] ?? 0) as $c)
                    <option value="{{ $c->id }}" {{ $category && $category->id === $c->id ? 'selected' : '' }}>
                        {{ $c->name }}@if(($needingHelp[$c->id] ?? 0) > 0) — {{ $needingHelp[$c->id] }} need you @endif
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

    @forelse($families as $family)
        <div class="bg-white shadow rounded-lg mb-4 border-l-4 {{ $family['first_stuck'] ? 'border-red-500' : 'border-amber-400' }}">
            <div class="p-4 flex flex-wrap items-start gap-6">

                {{-- The letter to start from, given the room it deserves. --}}
                <div class="text-center">
                    <div class="text-6xl leading-none font-semibold {{ $family['first_stuck'] ? 'text-red-600' : 'text-gray-800' }}"
                         style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $family['first']['word'] }}</div>
                    <div class="mt-1 text-xs uppercase tracking-wider {{ $family['first_stuck'] ? 'text-red-600 font-semibold' : 'text-gray-400' }}">
                        {{ $family['first_stuck'] ? 'start here' : 'first letter' }}
                    </div>
                </div>

                <div class="flex-1 min-w-[18rem]">
                    {{-- The whole row, in the order he practises it, each letter
                         carrying its own score so the shape of the trouble is
                         visible without reading anything. --}}
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($family['letters'] as $letter)
                            @php
                                $pct = $letter['accuracy'] === null ? null : round($letter['accuracy'] * 100);
                                $tone = match (true) {
                                    $pct === null => 'bg-gray-50 text-gray-400 border-gray-200',
                                    $pct < round($needsHelpBelow * 100) => 'bg-red-50 text-red-700 border-red-200',
                                    $pct < 65 => 'bg-amber-50 text-amber-700 border-amber-200',
                                    default => 'bg-green-50 text-green-700 border-green-200',
                                };
                            @endphp
                            <div class="border rounded px-2 py-1 text-center min-w-[3rem] {{ $tone }}">
                                <div class="text-xl leading-tight" style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $letter['word'] }}</div>
                                <div class="text-[0.65rem] leading-tight">{{ $pct === null ? '—' : $pct . '%' }}</div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 text-sm text-gray-600">
                        {{ $family['stuck'] }} of {{ count($family['letters']) }} stuck
                        @if($family['accuracy'] !== null)
                            · row average {{ round($family['accuracy'] * 100) }}%
                        @endif
                        · {{ $family['attempts'] }} {{ Str::plural('try', $family['attempts']) }}
                        @if($family['worst_streak'] > 2)
                            · <span class="text-red-600 font-medium">{{ $family['worst_streak'] }} misses in a row</span>
                        @endif
                    </div>

                    @if($family['heard'])
                        <div class="mt-2 text-sm text-gray-500">
                            <span class="text-xs uppercase tracking-wider text-gray-400">Heard for {{ $family['first']['word'] }}</span>
                            <span class="ml-1">
                                @foreach($family['heard'] as $heard)
                                    <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-gray-100 text-gray-700 mr-1"
                                          style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $heard['text'] }}<span class="text-xs text-gray-400">×{{ $heard['times'] }}</span></span>
                                @endforeach
                            </span>
                        </div>
                    @endif
                </div>

                <a href="{{ route('practice.level', ['categorySlug' => $category->slug, 'level' => $family['level']]) }}"
                   class="inline-flex items-center px-3 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white whitespace-nowrap">
                    Practise this row <i class="fas fa-arrow-right ml-1.5"></i>
                </a>
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500">
            Nothing in this category needs you right now — every family he has tried is above
            {{ round($needsHelpBelow * 100) }}%.
        </div>
    @endforelse
@endsection
