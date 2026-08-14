{{--
    Practice blocks — one row per day, positioned along a clock axis, so a
    day that reads as "78 attempts" in the columns above resolves into the
    two or three separate sittings it actually was.

    Position and width carry the whole message here: where a block sits is
    when practice happened, how wide it is is how long it lasted. That makes
    this the one chart on the page where an empty stretch is data.

    Same plain-SVG approach as the other two partials: no chart library, and
    the shared tooltip layer in index.blade.php drives the hit rects.

    Expects: $blockRows, $blockAxis, $blockShowsUser (from AttemptAnalyticsController)
--}}
@php
    $labelW = 150; $padTop = 26; $padRight = 16;
    $rowH = 34; $blockH = 13;

    $rowCount = count($blockRows);
    $plotW = 1100 - $labelW - $padRight;
    $plotTop = $padTop;
    $h = $padTop + $rowCount * $rowH + 10;

    $axisStart = $blockAxis['start'];
    $axisSpan = max(1, $blockAxis['end'] - $axisStart);

    // x for a minute-of-day, clamped so a block that starts before the axis
    // window can never be drawn off the left edge of the card.
    $xAt = function ($minute) use ($labelW, $plotW, $axisStart, $axisSpan) {
        $t = max(0, min(1, ($minute - $axisStart) / $axisSpan));
        return $labelW + $t * $plotW;
    };

    // At most ~12 hour labels; step up in whole hours so the ticks stay on
    // the hour rather than landing at 1:20, 2:40.
    $hours = (int) ceil($axisSpan / 60);
    $tickStep = max(1, (int) ceil($hours / 12)) * 60;

    $ticks = [];
    for ($m = $axisStart; $m <= $axisStart + $axisSpan; $m += $tickStep) {
        $hour = intdiv($m, 60) % 24;
        $ticks[] = [
            'x' => $xAt($m),
            'label' => $m >= 1440 ? '12a' : ($hour === 0 ? '12a' : ($hour === 12 ? '12p' : ($hour < 12 ? $hour . 'a' : ($hour - 12) . 'p'))),
        ];
    }

    $duration = function ($minutes) {
        $minutes = (int) round($minutes);
        if ($minutes < 1) {
            return 'under a minute';
        }
        return $minutes < 60 ? $minutes . ' min' : intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
    };

    // Hundreds of tab stops would make the page unusable by keyboard, so past
    // this many blocks the table view below is the keyboard path instead.
    $totalBlocks = array_sum(array_map(fn ($r) => count($r['blocks']), $blockRows));
    $focusable = $totalBlocks <= 60;
@endphp

<div class="viz-chart relative" data-viz-chart>
    <div class="overflow-x-auto">
        <svg width="1100" height="{{ $h }}" viewBox="0 0 1100 {{ $h }}"
             role="img"
             aria-label="Practice blocks by time of day, one row per day"
             style="display:block;">
            {{-- Hour grid: recessive, and behind everything. --}}
            @foreach($ticks as $tick)
                <line x1="{{ round($tick['x'], 2) }}" y1="{{ $plotTop }}"
                      x2="{{ round($tick['x'], 2) }}" y2="{{ $plotTop + $rowCount * $rowH }}"
                      stroke="var(--viz-grid)" stroke-width="1" />
                <text x="{{ round($tick['x'], 2) }}" y="{{ $padTop - 9 }}" text-anchor="middle"
                      fill="var(--viz-muted)" font-size="11">{{ $tick['label'] }}</text>
            @endforeach

            @foreach($blockRows as $i => $row)
                @php
                    $rowY = $plotTop + $i * $rowH;
                    $blockY = $rowY + 6;
                    // Zebra banding: with 30 rows the eye needs help tracking
                    // a block back to its date across 970px of empty axis.
                    $rowLabel = $row['date']->format('D, M j');
                    $subLabel = $blockShowsUser
                        ? ($row['user']?->name ?? 'Unknown')
                        : count($row['blocks']) . ' block' . (count($row['blocks']) === 1 ? '' : 's');
                    $labelledBlocks = count($row['blocks']) <= 6;
                    $lastLabelRight = -INF;
                @endphp

                @if($i % 2 === 1)
                    <rect x="{{ $labelW }}" y="{{ $rowY }}" width="{{ $plotW }}" height="{{ $rowH }}"
                          fill="#f9fafb" />
                @endif

                <text x="14" y="{{ $rowY + 15 }}" fill="var(--viz-ink)" font-size="11" font-weight="600">{{ $rowLabel }}</text>
                <text x="14" y="{{ $rowY + 27 }}" fill="var(--viz-muted)" font-size="10">{{ $subLabel }}</text>

                @foreach($row['blocks'] as $block)
                    @php
                        $x = $xAt($block['start_minute']);
                        // A single-attempt block has no duration at all, so
                        // every block gets a 3px floor — "it happened here" is
                        // the point, and an invisible mark can't say that.
                        $w = max(3, $xAt($block['end_minute']) - $x);

                        $tooltipRows = [
                            ['label' => 'long', 'value' => $duration($block['minutes']), 'color' => null],
                            ['label' => 'attempts', 'value' => number_format($block['attempts']), 'color' => null],
                            ['label' => 'correct', 'value' => number_format($block['correct']), 'color' => 'var(--viz-correct)'],
                            ['label' => 'incorrect', 'value' => number_format($block['incorrect']), 'color' => 'var(--viz-incorrect)'],
                            ['label' => 'accuracy', 'value' => $block['accuracy'] === null ? '—' : $block['accuracy'] . '%', 'color' => null],
                        ];

                        $tooltipTitle = $row['date']->format('D, M j') . ' · '
                            . $block['start']->format('g:i A') . ' – ' . $block['end']->format('g:i A')
                            . ($blockShowsUser && $row['user'] ? ' · ' . $row['user']->name : '');

                        // Hit target is always at least a comfortable pointer
                        // width, and overhangs the mark on both sides.
                        $hitW = max(20, $w + 12);
                        $hitX = $x + $w / 2 - $hitW / 2;

                        $labelText = $block['start']->format('g:i');
                        $labelX = $x + $w / 2;
                        $showLabel = $labelledBlocks && $w >= 22 && ($labelX - 16) > $lastLabelRight;
                        if ($showLabel) {
                            $lastLabelRight = $labelX + 16;
                        }
                    @endphp

                    <rect x="{{ round($x, 2) }}" y="{{ $blockY }}"
                          width="{{ round($w, 2) }}" height="{{ $blockH }}"
                          rx="{{ min(4, round($w / 2, 2)) }}" fill="var(--viz-correct)" />

                    @if($showLabel)
                        <text x="{{ round($labelX, 2) }}" y="{{ $rowY + 30 }}" text-anchor="middle"
                              fill="var(--viz-muted)" font-size="9">{{ $labelText }}</text>
                    @endif

                    <rect class="viz-hit" x="{{ round($hitX, 2) }}" y="{{ $rowY }}"
                          width="{{ round($hitW, 2) }}" height="{{ $rowH }}"
                          fill="transparent" @if($focusable) tabindex="0" role="img" @endif
                          data-viz-hit
                          data-title="{{ $tooltipTitle }}"
                          data-rows="{{ json_encode($tooltipRows) }}">
                        <title>{{ $tooltipTitle }}: {{ $block['attempts'] }} attempts over {{ $duration($block['minutes']) }}</title>
                    </rect>
                @endforeach
            @endforeach

            {{-- Baseline under the last row, matching the other two charts. --}}
            <line x1="{{ $labelW }}" y1="{{ $plotTop + $rowCount * $rowH }}"
                  x2="{{ 1100 - $padRight }}" y2="{{ $plotTop + $rowCount * $rowH }}"
                  stroke="var(--viz-axis)" stroke-width="1" />
        </svg>
    </div>

    <div class="viz-tooltip" data-viz-tooltip hidden></div>
</div>
