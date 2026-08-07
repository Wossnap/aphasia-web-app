{{--
    Accuracy per period — a separate chart rather than a second axis on the
    columns above: percentage and count are different scales, and overlaying
    them would invent a relationship the data doesn't have.

    The x geometry deliberately matches columns.blade.php so the two charts
    line up period-for-period when read together.

    Expects: $buckets (from AttemptAnalyticsController)
--}}
@php
    $n = count($buckets);

    $axisW = 48; $padTop = 20; $padRight = 16; $padBottom = 44;
    $plotH = 160;
    $h = $padTop + $plotH + $padBottom;

    $bandW = $n > 0 ? min(72, max(28, (1100 - $padRight) / $n)) : 28;
    $groupW = $n * $bandW;
    $plotW = max(1100, $groupW + $padRight);
    $x0 = ($plotW - $padRight - $groupW) / 2;
    $baseline = $padTop + $plotH;

    $every = max(1, (int) ceil($n / 12));
    $focusable = $n <= 40;

    // Periods with no attempts have no accuracy, so the line breaks there
    // instead of dropping to 0% and implying a bad session.
    $segments = [];
    $current = [];
    foreach ($buckets as $i => $bucket) {
        if ($bucket['accuracy'] === null) {
            if (count($current) > 0) { $segments[] = $current; $current = []; }
            continue;
        }
        $current[] = [
            'x' => $x0 + $i * $bandW + $bandW / 2,
            'y' => $baseline - ($bucket['accuracy'] / 100) * $plotH,
            'accuracy' => $bucket['accuracy'],
        ];
    }
    if (count($current) > 0) { $segments[] = $current; }

    $points = $segments ? array_merge(...$segments) : [];
    $showMarkers = count($points) <= 40;
@endphp

<div class="viz-chart relative flex items-start" data-viz-chart>
    <svg width="{{ $axisW }}" height="{{ $h }}" viewBox="0 0 {{ $axisW }} {{ $h }}"
         class="flex-none" aria-hidden="true" style="display:block;">
        @for($i = 0; $i <= 4; $i++)
            @php $value = $i * 25; $y = $baseline - ($value / 100) * $plotH; @endphp
            <text x="{{ $axisW - 10 }}" y="{{ round($y + 4, 2) }}" text-anchor="end"
                  fill="var(--viz-muted)" font-size="11" style="font-variant-numeric: tabular-nums;">{{ $value }}%</text>
        @endfor
    </svg>

    <div class="overflow-x-auto flex-1 min-w-0" data-viz-scroll>
        <svg width="{{ $plotW }}" height="{{ $h }}" viewBox="0 0 {{ $plotW }} {{ $h }}"
             role="img" aria-label="Accuracy per period, as a percentage"
             style="display:block;">
            @for($i = 0; $i <= 4; $i++)
                @php $y = $baseline - (($i * 25) / 100) * $plotH; @endphp
                <line x1="0" y1="{{ round($y, 2) }}" x2="{{ $plotW }}" y2="{{ round($y, 2) }}"
                      stroke="{{ $i === 0 ? 'var(--viz-axis)' : 'var(--viz-grid)' }}" stroke-width="1" />
            @endfor

            {{-- Crosshair: readers aim at a period, not at a 2px line. --}}
            <line class="viz-crosshair" data-viz-crosshair x1="0" y1="{{ $padTop }}" x2="0" y2="{{ $baseline }}"
                  stroke="var(--viz-axis)" stroke-width="1" opacity="0" />

            @foreach($segments as $segment)
                @if(count($segment) > 1)
                    <polyline points="{{ collect($segment)->map(fn ($p) => round($p['x'], 2) . ',' . round($p['y'], 2))->implode(' ') }}"
                              fill="none" stroke="var(--viz-accuracy)" stroke-width="2"
                              stroke-linejoin="round" stroke-linecap="round" />
                @endif
            @endforeach

            {{-- Markers double as the only mark a one-period-long segment has,
                 so an isolated session is never invisible. --}}
            @foreach($points as $point)
                @if($showMarkers || count($points) === 1)
                    <circle cx="{{ round($point['x'], 2) }}" cy="{{ round($point['y'], 2) }}" r="4"
                            fill="var(--viz-accuracy)" stroke="var(--viz-surface)" stroke-width="2" />
                @endif
            @endforeach

            {{-- Endpoint gets the one direct label; the rest live on the axis,
                 in the tooltip and in the table. --}}
            @if(count($points) > 0)
                @php $last = $points[count($points) - 1]; @endphp
                <text x="{{ round($last['x'] + 10, 2) }}" y="{{ round($last['y'] + 4, 2) }}"
                      text-anchor="{{ $last['x'] > $plotW - 60 ? 'end' : 'start' }}"
                      fill="var(--viz-ink)" font-size="12" font-weight="600">{{ $last['accuracy'] }}%</text>
            @endif

            @foreach($buckets as $i => $bucket)
                @php
                    $bandX = $x0 + $i * $bandW;
                    $tooltipRows = [
                        ['label' => 'accuracy', 'value' => $bucket['accuracy'] === null ? 'No attempts' : $bucket['accuracy'] . '%', 'color' => 'var(--viz-accuracy)'],
                        ['label' => 'correct of attempts', 'value' => number_format($bucket['correct']) . ' of ' . number_format($bucket['attempts']), 'color' => null],
                    ];
                @endphp

                @if($i % $every === 0)
                    <text x="{{ round($bandX + $bandW / 2, 2) }}" y="{{ $baseline + 20 }}" text-anchor="middle"
                          fill="var(--viz-muted)" font-size="11">{{ $bucket['label'] }}</text>
                @endif

                <rect class="viz-hit" x="{{ round($bandX, 2) }}" y="{{ $padTop }}"
                      width="{{ round($bandW, 2) }}" height="{{ $plotH }}"
                      fill="transparent" @if($focusable) tabindex="0" role="img" @endif
                      data-viz-hit
                      data-crosshair="{{ round($bandX + $bandW / 2, 2) }}"
                      data-title="{{ $bucket['full_label'] }}"
                      data-rows="{{ json_encode($tooltipRows) }}">
                    <title>{{ $bucket['full_label'] }}: {{ $bucket['accuracy'] === null ? 'no attempts' : $bucket['accuracy'] . '% accuracy' }}</title>
                </rect>
            @endforeach
        </svg>
    </div>

    <div class="viz-tooltip" data-viz-tooltip hidden></div>
</div>
