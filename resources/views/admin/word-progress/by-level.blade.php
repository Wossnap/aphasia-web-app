@extends('admin.layout')

@section('title', 'Progress By Level')
@section('header', 'Progress By Level')

@section('content')
    <p class="mb-4 text-sm text-gray-600 max-w-3xl">
        Every level in the category, first to last, with each letter's own score. Green is holding,
        amber is on the way, red is stuck, grey has not been tried yet.
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

    @if($levels->isNotEmpty())
        <div class="mb-6 flex flex-wrap gap-3">
            @foreach([
                ['Holding', $summary['strong'], 'text-green-700', 'border-green-300'],
                ['On the way', $summary['middling'], 'text-amber-700', 'border-amber-300'],
                ['Stuck', $summary['weak'], 'text-red-700', 'border-red-300'],
                ['Not started', $summary['untried'], 'text-gray-500', 'border-gray-300'],
            ] as [$label, $count, $text, $border])
                <div class="bg-white shadow rounded-lg px-4 py-3 border-l-4 {{ $border }}">
                    <div class="text-2xl font-semibold {{ $text }}">{{ $count }}</div>
                    <div class="text-xs text-gray-500 uppercase tracking-wider">{{ $label }}</div>
                </div>
            @endforeach
            <div class="bg-white shadow rounded-lg px-4 py-3 border-l-4 border-blue-300">
                <div class="text-2xl font-semibold text-blue-700">{{ number_format($summary['attempts']) }}</div>
                <div class="text-xs text-gray-500 uppercase tracking-wider">Tries in all</div>
            </div>
        </div>
    @endif

    @forelse($levels as $level)
        @php
            $pct = $level['accuracy'] === null ? null : round($level['accuracy'] * 100);
            $edge = match (true) {
                $pct === null => 'border-gray-300',
                $pct < round($needsHelpBelow * 100) => 'border-red-500',
                $pct < 65 => 'border-amber-400',
                default => 'border-green-500',
            };
        @endphp
        <div class="bg-white shadow rounded-lg mb-3 border-l-4 {{ $edge }}">
            <div class="p-4 flex flex-wrap items-center gap-5">
                <div class="text-center min-w-[4rem]">
                    <div class="text-4xl leading-none font-semibold text-gray-800"
                         style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $level['label'] }}</div>
                    <div class="mt-1 text-xs text-gray-400">level {{ $level['level'] }}</div>
                </div>

                <div class="flex-1 min-w-[18rem]">
                    @include('admin.partials.letter-row', [
                        'letters' => $level['letters'],
                        'needsHelpBelow' => $needsHelpBelow,
                    ])
                </div>

                <div class="text-right min-w-[9rem]">
                    <div class="text-2xl font-semibold {{ $pct === null ? 'text-gray-400' : ($pct < 45 ? 'text-red-600' : ($pct < 65 ? 'text-amber-600' : 'text-green-600')) }}">
                        {{ $pct === null ? '—' : $pct . '%' }}
                    </div>
                    <div class="text-xs text-gray-500">
                        {{ $level['strong'] }}/{{ $level['size'] }} holding
                        @if($level['untried'] > 0) · {{ $level['untried'] }} not started @endif
                    </div>
                    <a href="{{ route('practice.level', ['categorySlug' => $category->slug, 'level' => $level['level']]) }}"
                       class="mt-1 inline-block text-sm text-blue-600 hover:text-blue-800">
                        Practise <i class="fas fa-arrow-right ml-0.5"></i>
                    </a>
                </div>
            </div>
        </div>
    @empty
        <div class="bg-white shadow rounded-lg p-6 text-center text-gray-500">
            This category has no levels to show yet.
        </div>
    @endforelse
@endsection
