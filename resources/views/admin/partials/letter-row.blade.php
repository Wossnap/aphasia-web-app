{{--
    One level's letters, in the order he practises them, each carrying its own
    score. Shared by the work-with-him list and the by-level progress page so
    the two always read the same and cannot drift apart.

    $letters          rows from ItemStats, already in practice order
    $needsHelpBelow   fraction under which a letter counts as stuck
--}}
@php
    $stuckAt = round(($needsHelpBelow ?? 0.25) * 100);
@endphp

<div class="flex flex-wrap gap-1.5">
    @foreach($letters as $letter)
        @php
            $pct = $letter['accuracy'] === null ? null : round($letter['accuracy'] * 100);
            $tone = match (true) {
                $pct === null => 'bg-gray-50 text-gray-400 border-gray-200',
                $pct < $stuckAt => 'bg-red-50 text-red-700 border-red-200',
                $pct < 65 => 'bg-amber-50 text-amber-700 border-amber-200',
                $pct < 85 => 'bg-green-50 text-green-700 border-green-200',
                default => 'bg-green-100 text-green-800 border-green-300',
            };
        @endphp
        <div class="border rounded px-2 py-1 text-center min-w-[3rem] {{ $tone }}"
             title="{{ $letter['attempts'] }} {{ Str::plural('try', $letter['attempts']) }}">
            <div class="text-xl leading-tight" style="font-family: 'Noto Sans Ethiopic', sans-serif;">{{ $letter['word'] }}</div>
            <div class="text-[0.65rem] leading-tight">{{ $pct === null ? '—' : $pct . '%' }}</div>
        </div>
    @endforeach
</div>
