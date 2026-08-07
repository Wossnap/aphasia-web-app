{{--
    Attempts per period — stacked columns, Correct on the baseline and
    Incorrect stacked above it, so column height reads as total volume and
    the split reads as quality.

    Geometry is computed here and emitted as plain SVG: no chart library, in
    keeping with the rest of the admin. Both SVGs are sized in real pixels
    rather than scaled to the card, so the pinned y-axis on the left always
    lines up with the gridlines in the scrolling plot beside it.

    Expects: $buckets (from AttemptAnalyticsController)
--}}
@php
    $n = count($buckets);

    $axisW = 48; $padTop = 20; $padRight = 16; $padBottom = 44;
    $plotH = 230;
    $h = $padTop + $plotH + $padBottom;

    // 1100 is roughly the inner width of an admin card on a desktop screen:
    // at the default 30-day daily view nothing overflows and no scrolling is
    // needed. Bands are floored at 28px so labels and hit targets survive,
    // and capped at 72px so a three-column year view stays a chart rather
    // than three billboards.
    $bandW = $n > 0 ? min(72, max(28, (1100 - $padRight) / $n)) : 28;
    $groupW = $n * $bandW;
    $plotW = max(1100, $groupW + $padRight);
    $x0 = ($plotW - $padRight - $groupW) / 2;
    $barW = min(24, max(2, $bandW - 4));
    $baseline = $padTop + $plotH;

    // Y axis: four bands topped by a round number, so ticks read 0/5/10/15/20.
    $maxAttempts = $n > 0 ? max(array_column($buckets, 'attempts')) : 0;
    $rough = max($maxAttempts, 1) / 4;
    $mag = pow(10, floor(log10($rough)));
    $step = $mag;
    foreach ([1, 2, 2.5, 5, 10] as $m) {
        if ($rough <= $m * $mag) { $step = $m * $mag; break; }
    }
    $step = max(1, (int) ceil($step));
    $top = $step * 4;

    // At most ~12 x-axis labels, evenly spaced; the rest are carried by the
    // tooltip and the table view below.
    $every = max(1, (int) ceil($n / 12));

    // Hundreds of tab stops would make the page unusable by keyboard, so past
    // this many periods the table view is the keyboard path instead.
    $focusable = $n <= 40;

    $roundedTop = function ($x, $y, $width, $height, $r = 4) {
        $r = min($r, $width / 2, $height);
        if ($r <= 0.5) {
            return sprintf('M %.2f %.2f h %.2f v %.2f h %.2f Z', $x, $y, $width, $height, -$width);
        }
        return sprintf(
            'M %.2f %.2f V %.2f Q %.2f %.2f %.2f %.2f H %.2f Q %.2f %.2f %.2f %.2f V %.2f Z',
            $x, $y + $height,
            $y + $r,
            $x, $y, $x + $r, $y,
            $x + $width - $r,
            $x + $width, $y, $x + $width, $y + $r,
            $y + $height
        );
    };
@endphp

<div class="viz-chart relative flex items-start" data-viz-chart>
    {{-- Pinned value axis: it must stay readable however far the plot beside
         it is scrolled. --}}
    <svg width="{{ $axisW }}" height="{{ $h }}" viewBox="0 0 {{ $axisW }} {{ $h }}"
         class="flex-none" aria-hidden="true" style="display:block;">
        @for($i = 0; $i <= 4; $i++)
            @php $value = $step * $i; $y = $baseline - ($value / $top) * $plotH; @endphp
            <text x="{{ $axisW - 10 }}" y="{{ round($y + 4, 2) }}" text-anchor="end"
                  fill="var(--viz-muted)" font-size="11" style="font-variant-numeric: tabular-nums;">{{ number_format($value) }}</text>
        @endfor
    </svg>

    <div class="overflow-x-auto flex-1 min-w-0" data-viz-scroll>
        <svg width="{{ $plotW }}" height="{{ $h }}" viewBox="0 0 {{ $plotW }} {{ $h }}"
             role="img" aria-label="Attempts per period, split into correct and incorrect"
             style="display:block;">
            @for($i = 0; $i <= 4; $i++)
                @php $y = $baseline - (($step * $i) / $top) * $plotH; @endphp
                <line x1="0" y1="{{ round($y, 2) }}" x2="{{ $plotW }}" y2="{{ round($y, 2) }}"
                      stroke="{{ $i === 0 ? 'var(--viz-axis)' : 'var(--viz-grid)' }}" stroke-width="1" />
            @endfor

            @foreach($buckets as $i => $bucket)
                @php
                    $bandX = $x0 + $i * $bandW;
                    $barX = $bandX + ($bandW - $barW) / 2;

                    // Nonzero values floor at 2px so a single attempt never
                    // renders as an invisible sliver.
                    $hCorrect = $bucket['correct'] > 0 ? max(2, ($bucket['correct'] / $top) * $plotH) : 0;
                    $hIncorrect = $bucket['incorrect'] > 0 ? max(2, ($bucket['incorrect'] / $top) * $plotH) : 0;

                    $yCorrectTop = $baseline - $hCorrect;
                    $yIncorrectTop = $yCorrectTop - $hIncorrect;
                    $stackTop = $bucket['attempts'] > 0 ? $yIncorrectTop : $baseline;

                    // 2px of surface separates the two fills; the lower one
                    // gives up the space so the stack stays baseline-anchored.
                    $bothPresent = $hCorrect > 0 && $hIncorrect > 0;
                    $correctDrawTop = $yCorrectTop + ($bothPresent ? 2 : 0);
                    $correctDrawH = $baseline - $correctDrawTop;

                    $tooltipRows = [
                        ['label' => 'attempts', 'value' => number_format($bucket['attempts']), 'color' => null],
                        ['label' => 'correct', 'value' => number_format($bucket['correct']), 'color' => 'var(--viz-correct)'],
                        ['label' => 'incorrect', 'value' => number_format($bucket['incorrect']), 'color' => 'var(--viz-incorrect)'],
                        ['label' => 'accuracy', 'value' => $bucket['accuracy'] === null ? '—' : $bucket['accuracy'] . '%', 'color' => null],
                    ];
                @endphp

                @if($hIncorrect > 0)
                    <path d="{{ $roundedTop($barX, $yIncorrectTop, $barW, $hIncorrect) }}" fill="var(--viz-incorrect)" />
                @endif

                @if($correctDrawH > 0)
                    @if($hIncorrect > 0)
                        <rect x="{{ round($barX, 2) }}" y="{{ round($correctDrawTop, 2) }}"
                              width="{{ round($barW, 2) }}" height="{{ round($correctDrawH, 2) }}" fill="var(--viz-correct)" />
                    @else
                        <path d="{{ $roundedTop($barX, $correctDrawTop, $barW, $correctDrawH) }}" fill="var(--viz-correct)" />
                    @endif
                @endif

                {{-- Direct labels only while they comfortably fit; past that the
                     tooltip and the table carry the numbers. --}}
                @if($n <= 12 && $bucket['attempts'] > 0)
                    <text x="{{ round($bandX + $bandW / 2, 2) }}" y="{{ round($stackTop - 8, 2) }}" text-anchor="middle"
                          fill="var(--viz-ink)" font-size="12" font-weight="600">{{ number_format($bucket["attempts"]) }}</text>
                @endif

                @if($i % $every === 0)
                    <text x="{{ round($bandX + $bandW / 2, 2) }}" y="{{ $baseline + 20 }}" text-anchor="middle"
                          fill="var(--viz-muted)" font-size="11">{{ $bucket['label'] }}</text>
                @endif

                {{-- The hit target is the whole band, never just the painted
                     bar: the pointer only has to be nearest, not dead-centre. --}}
                <rect class="viz-hit" x="{{ round($bandX, 2) }}" y="{{ $padTop }}"
                      width="{{ round($bandW, 2) }}" height="{{ $plotH }}"
                      fill="transparent" @if($focusable) tabindex="0" role="img" @endif
                      data-viz-hit
                      data-title="{{ $bucket['full_label'] }}"
                      data-rows="{{ json_encode($tooltipRows) }}">
                    <title>{{ $bucket['full_label'] }}: {{ $bucket['attempts'] }} attempts</title>
                </rect>
            @endforeach
        </svg>
    </div>

    <div class="viz-tooltip" data-viz-tooltip hidden></div>
</div>
