@extends('admin.layout')

@section('title', 'Attempt Analytics')
@section('header', 'Attempt Analytics')

@section('content')
    {{--
        Chart colours live here as custom properties so the two charts share
        one definition. Correct/Incorrect use the first two slots of the
        validated categorical order (blue, orange) rather than the obvious
        green/red: green-vs-red is the classic red-green colourblind pair and
        fails CVD separation outright when the two sit touching in a stack.
        The admin has no dark theme, so these are light-surface steps only.
    --}}
    <style>
        .viz-root {
            --viz-surface:    #ffffff;
            --viz-correct:    #2a78d6;
            --viz-incorrect:  #eb6834;
            --viz-accuracy:   #2a78d6;
            --viz-grid:       #e5e7eb;
            --viz-axis:       #d1d5db;
            --viz-muted:      #6b7280;
            --viz-ink:        #111827;
        }
        .viz-hit { cursor: default; outline: none; }
        .viz-hit:hover, .viz-hit:focus-visible { fill: rgba(17, 24, 39, 0.04); }
        .viz-tooltip {
            position: absolute;
            z-index: 30;
            pointer-events: none;
            min-width: 9rem;
            padding: 0.5rem 0.625rem;
            border-radius: 0.375rem;
            background: var(--viz-surface);
            border: 1px solid var(--viz-grid);
            box-shadow: 0 4px 12px rgba(17, 24, 39, 0.12);
            font-size: 0.75rem;
            line-height: 1.35;
        }
        .viz-tooltip-title { color: var(--viz-muted); margin-bottom: 0.25rem; }
        .viz-tooltip-row { display: flex; align-items: center; gap: 0.375rem; white-space: nowrap; }
        /* Line key rather than a filled box: at tooltip density a swatch is
           data-weight ink doing a label's job. */
        .viz-tooltip-key { width: 10px; height: 2px; border-radius: 1px; flex: none; }
        .viz-tooltip-value { font-weight: 700; color: var(--viz-ink); font-variant-numeric: tabular-nums; }
        .viz-tooltip-label { color: var(--viz-muted); }
    </style>

    <div class="viz-root">
        {{-- One filter row, above everything it scopes: every tile, chart and
             table below re-renders against this same slice. --}}
        <form method="GET" class="mb-6 bg-white shadow rounded-lg p-4 flex flex-wrap items-end gap-4">
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
                <label for="range" class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Date range</label>
                <select name="range" id="range" data-range-select
                        class="block w-44 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach(\App\Http\Controllers\Admin\AttemptAnalyticsController::RANGES as $value => $label)
                        <option value="{{ $value }}" {{ $range === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div data-custom-range class="{{ $range === 'custom' ? 'flex' : 'hidden' }} items-end gap-2">
                <div>
                    <label for="from" class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">From</label>
                    <input type="date" name="from" id="from" value="{{ $from }}"
                           class="block border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="to" class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">To</label>
                    <input type="date" name="to" id="to" value="{{ $to }}"
                           class="block border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <div>
                <label for="granularity" class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">Group by</label>
                <select name="granularity" id="granularity"
                        class="block w-36 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach(\App\Http\Controllers\Admin\AttemptAnalyticsController::GRANULARITIES as $value => $label)
                        <option value="{{ $value }}" {{ $granularity === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="gap" class="block text-xs font-medium text-gray-500 uppercase tracking-wider mb-1">New block after</label>
                <select name="gap" id="gap"
                        class="block w-36 border-gray-300 rounded-md shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500">
                    @foreach(\App\Http\Controllers\Admin\AttemptAnalyticsController::BLOCK_GAPS as $value => $label)
                        <option value="{{ $value }}" {{ $gap === $value ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center gap-2">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-md bg-blue-600 hover:bg-blue-700 text-white shadow-sm">
                    <i class="fas fa-chart-column mr-1.5"></i> Apply
                </button>
                @if($userId || $range !== '30d' || $granularity !== 'daily' || request()->has('gap'))
                    <a href="{{ route('admin.analytics.index') }}"
                       class="inline-flex items-center px-4 py-2 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 text-gray-700">
                        Reset
                    </a>
                @endif
            </div>
        </form>

        @if($truncated)
            <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                <i class="fas fa-circle-info mr-1"></i>
                That range holds more periods than one chart can show, so only the most recent 400 are plotted.
                Switch <strong>Group by</strong> to a coarser setting to see the whole range.
            </div>
        @endif

        {{-- Hero + supporting tiles. The headline number is a figure, not a
             one-bar chart. --}}
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 mb-6">
            <div class="bg-white shadow rounded-lg p-5 lg:col-span-1">
                <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Total attempts</div>
                <div class="text-5xl font-semibold text-gray-900 mt-1 leading-none">{{ number_format($stats['attempts']) }}</div>
                <div class="text-sm text-gray-500 mt-2">
                    {{ $selectedUser?->name ?? 'All users' }} &middot;
                    {{ \Illuminate\Support\Carbon::parse($from)->format('M j, Y') }} – {{ \Illuminate\Support\Carbon::parse($to)->format('M j, Y') }}
                </div>
            </div>

            <div class="lg:col-span-3 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white shadow rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">
                        {{ $stats['accuracy'] === null ? '—' : $stats['accuracy'] . '%' }}
                    </div>
                    <div class="text-xs text-gray-500 mt-1">
                        {{ number_format($stats['correct']) }} correct, {{ number_format($stats['incorrect']) }} not
                    </div>
                </div>

                <div class="bg-white shadow rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Active periods</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">{{ $stats['active_periods'] }}</div>
                    <div class="text-xs text-gray-500 mt-1">of {{ $stats['total_periods'] }} in range</div>
                </div>

                <div class="bg-white shadow rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Avg per active</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($stats['avg_per_active'], 1) }}</div>
                    <div class="text-xs text-gray-500 mt-1">attempts per active period</div>
                </div>

                <div class="bg-white shadow rounded-lg p-4">
                    <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Busiest period</div>
                    <div class="text-2xl font-semibold text-gray-900 mt-1">{{ isset($stats['busiest']) ? number_format($stats['busiest']['attempts']) : '—' }}</div>
                    <div class="text-xs text-gray-500 mt-1 truncate">{{ $stats['busiest']['full_label'] ?? 'No attempts yet' }}</div>
                </div>
            </div>
        </div>

        @if($stats['attempts'] === 0)
            <div class="bg-white shadow rounded-lg px-6 py-16 text-center">
                <div class="text-4xl text-gray-300 mb-3"><i class="fas fa-chart-column"></i></div>
                <p class="text-gray-900 font-medium">No attempts in this range.</p>
                <p class="text-sm text-gray-500 mt-1">
                    Try a wider date range{{ $selectedUser ? ' or clear the user filter' : '' }}.
                </p>
            </div>
        @else
            <!-- Attempts over time -->
            <div class="bg-white shadow rounded-lg p-5 mb-6">
                <div class="flex flex-wrap items-baseline justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Attempts over time</h2>
                        <p class="text-sm text-gray-500">{{ \App\Http\Controllers\Admin\AttemptAnalyticsController::GRANULARITIES[$granularity] }} totals, split by outcome.</p>
                    </div>
                    {{-- Legend is always present for two series, so identity is
                         never carried by colour alone. --}}
                    <div class="flex items-center gap-4 text-xs">
                        <span class="flex items-center gap-1.5 text-gray-600">
                            <span class="inline-block w-3 h-3 rounded-sm" style="background: var(--viz-correct)"></span> Correct
                        </span>
                        <span class="flex items-center gap-1.5 text-gray-600">
                            <span class="inline-block w-3 h-3 rounded-sm" style="background: var(--viz-incorrect)"></span> Incorrect
                        </span>
                    </div>
                </div>

                @include('admin.analytics.partials.columns')
            </div>

            <!-- Accuracy over time -->
            <div class="bg-white shadow rounded-lg p-5 mb-6">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-900">Accuracy over time</h2>
                    <p class="text-sm text-gray-500">Share of attempts marked correct. Periods with no attempts leave a gap.</p>
                </div>

                @include('admin.analytics.partials.accuracy')
            </div>

            {{-- Practice blocks: the only section that looks inside a day.
                 Everything above collapses a day to one number, which cannot
                 tell one long sitting from three short ones. --}}
            @php
                $fmtDuration = function ($minutes) {
                    $minutes = (int) round($minutes);
                    if ($minutes < 1) return 'under a minute';
                    return $minutes < 60 ? $minutes . ' min' : intdiv($minutes, 60) . 'h ' . ($minutes % 60) . 'm';
                };
            @endphp
            <div class="bg-white shadow rounded-lg p-5 mb-6">
                <div class="flex flex-wrap items-baseline justify-between gap-3 mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-gray-900">Practice blocks</h2>
                        <p class="text-sm text-gray-500">
                            When practice actually happened, by time of day. A pause longer than
                            <strong>{{ \App\Http\Controllers\Admin\AttemptAnalyticsController::BLOCK_GAPS[$gap] }}</strong>
                            ends one block and starts the next.
                        </p>
                    </div>
                    <p class="text-xs text-gray-400">Newest day first</p>
                </div>

                @if(empty($blockRows))
                    <div class="px-6 py-10 text-center">
                        <p class="text-gray-900 font-medium">No practice blocks in this range.</p>
                        <p class="text-sm text-gray-500 mt-1">
                            Blocks are built per person, so attempts recorded without a signed-in user are left out.
                        </p>
                    </div>
                @else
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Blocks</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($blockStats['blocks']) }}</div>
                            <div class="text-xs text-gray-500 mt-1">across {{ $blockStats['days'] }} {{ $blockStats['days'] === 1 ? 'day' : 'days' }}</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Blocks per day</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ number_format($blockStats['avg_per_day'], 1) }}</div>
                            <div class="text-xs text-gray-500 mt-1">on days with practice</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Typical block</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ $fmtDuration($blockStats['avg_minutes']) }}</div>
                            <div class="text-xs text-gray-500 mt-1">first to last attempt</div>
                        </div>
                        <div class="rounded-lg border border-gray-200 p-3">
                            <div class="text-xs font-medium text-gray-500 uppercase tracking-wider">Longest block</div>
                            <div class="text-2xl font-semibold text-gray-900 mt-1">{{ $blockStats['longest'] ? $fmtDuration($blockStats['longest']['minutes']) : '—' }}</div>
                            <div class="text-xs text-gray-500 mt-1 truncate">
                                {{ $blockStats['longest'] ? $blockStats['longest']['date']->format('D, M j') : 'No blocks yet' }}
                            </div>
                        </div>
                    </div>

                    @if($blockRowsTruncated > 0)
                        <div class="mb-4 bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded text-sm">
                            <i class="fas fa-circle-info mr-1"></i>
                            Showing the 30 most recent days{{ $blockShowsUser ? ' and users' : '' }};
                            {{ $blockRowsTruncated }} older {{ $blockRowsTruncated === 1 ? 'row' : 'rows' }} not plotted.
                            Narrow the date range to see them.
                        </div>
                    @endif

                    @include('admin.analytics.partials.practice-blocks')

                    <details class="mt-4 border-t border-gray-200 pt-3">
                        <summary class="cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900 select-none">
                            <i class="fas fa-table mr-1.5 text-gray-400"></i> View blocks as table
                        </summary>
                        <div class="overflow-x-auto mt-3">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Day</th>
                                        @if($blockShowsUser)
                                            <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        @endif
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Block</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Length</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @foreach($blockRows as $row)
                                        @foreach($row['blocks'] as $b => $block)
                                            <tr class="text-gray-900">
                                                <td class="px-4 py-2 whitespace-nowrap text-sm {{ $b > 0 ? 'text-gray-400' : '' }}">
                                                    {{ $b === 0 ? $row['date']->format('D, M j, Y') : '' }}
                                                </td>
                                                @if($blockShowsUser)
                                                    <td class="px-4 py-2 whitespace-nowrap text-sm {{ $b > 0 ? 'text-gray-400' : '' }}">
                                                        {{ $b === 0 ? ($row['user']?->name ?? 'Unknown') : '' }}
                                                    </td>
                                                @endif
                                                <td class="px-4 py-2 whitespace-nowrap text-sm tabular-nums">
                                                    {{ $block['start']->format('g:i A') }} – {{ $block['end']->format('g:i A') }}
                                                </td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right tabular-nums">{{ $fmtDuration($block['minutes']) }}</td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right tabular-nums font-medium">{{ number_format($block['attempts']) }}</td>
                                                <td class="px-4 py-2 whitespace-nowrap text-sm text-right tabular-nums">{{ $block['accuracy'] === null ? '—' : $block['accuracy'] . '%' }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </div>

            @if($topUsers->isNotEmpty())
                <!-- Most active users -->
                <div class="bg-white shadow rounded-lg p-5 mb-6">
                    <div class="mb-4">
                        <h2 class="text-base font-semibold text-gray-900">Most active users</h2>
                        <p class="text-sm text-gray-500">Attempts in this range. Pick one to scope every chart above to them.</p>
                    </div>

                    @php $peak = $topUsers->max('attempts'); @endphp
                    <div class="space-y-2.5">
                        @foreach($topUsers as $row)
                            @php $pct = $peak > 0 ? max(2, round($row['attempts'] / $peak * 100, 1)) : 0; @endphp
                            <a href="{{ request()->fullUrlWithQuery(['user_id' => $row['user']->id]) }}"
                               class="group grid grid-cols-[minmax(0,10rem)_1fr_auto] items-center gap-3 rounded px-1 py-0.5 hover:bg-gray-50">
                                <span class="text-sm text-gray-700 truncate group-hover:text-blue-700">{{ $row['user']->name }}</span>
                                <span class="block h-4">
                                    <span class="block h-full" style="width: {{ $pct }}%; background: var(--viz-correct); border-radius: 0 4px 4px 0;"></span>
                                </span>
                                <span class="text-sm font-semibold text-gray-900 tabular-nums w-12 text-right">{{ number_format($row['attempts']) }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Table twin: every value in the charts is readable here without
                 hovering anything. --}}
            <details class="bg-white shadow rounded-lg">
                <summary class="px-5 py-4 cursor-pointer text-sm font-medium text-gray-700 hover:text-gray-900 select-none">
                    <i class="fas fa-table mr-1.5 text-gray-400"></i> View as table
                </summary>
                <div class="overflow-x-auto border-t border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Period</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Attempts</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Correct</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Incorrect</th>
                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Accuracy</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @foreach($buckets as $bucket)
                                <tr class="{{ $bucket['attempts'] === 0 ? 'text-gray-400' : 'text-gray-900' }}">
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm">{{ $bucket['full_label'] }}</td>
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm text-right tabular-nums font-medium">{{ number_format($bucket['attempts']) }}</td>
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm text-right tabular-nums">{{ number_format($bucket['correct']) }}</td>
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm text-right tabular-nums">{{ number_format($bucket['incorrect']) }}</td>
                                    <td class="px-6 py-2.5 whitespace-nowrap text-sm text-right tabular-nums">{{ $bucket['accuracy'] === null ? '—' : $bucket['accuracy'] . '%' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-gray-50 font-semibold text-gray-900">
                            <tr>
                                <td class="px-6 py-3 text-sm">Total</td>
                                <td class="px-6 py-3 text-sm text-right tabular-nums">{{ number_format($stats['attempts']) }}</td>
                                <td class="px-6 py-3 text-sm text-right tabular-nums">{{ number_format($stats['correct']) }}</td>
                                <td class="px-6 py-3 text-sm text-right tabular-nums">{{ number_format($stats['incorrect']) }}</td>
                                <td class="px-6 py-3 text-sm text-right tabular-nums">{{ $stats['accuracy'] === null ? '—' : $stats['accuracy'] . '%' }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </details>
        @endif
    </div>

    @include('admin.partials.searchable-select-script')

    <script>
        // The custom From/To pair is only meaningful for the "Custom range"
        // preset, so it follows the select instead of sitting there inert.
        (function () {
            const select = document.querySelector('[data-range-select]');
            const custom = document.querySelector('[data-custom-range]');
            if (!select || !custom) return;

            select.addEventListener('change', function () {
                const isCustom = select.value === 'custom';
                custom.classList.toggle('hidden', !isCustom);
                custom.classList.toggle('flex', isCustom);
            });
        })();

        // Chart hover/focus layer. Each band carries a transparent hit rect;
        // the tooltip is HTML so it can never be clipped by the SVG viewBox,
        // and it only ever enhances — the same numbers are in the table view.
        document.querySelectorAll('[data-viz-chart]').forEach(function (chart) {
            const tooltip = chart.querySelector('[data-viz-tooltip]');
            const crosshair = chart.querySelector('[data-viz-crosshair]');
            const scroller = chart.querySelector('[data-viz-scroll]');
            const svg = chart.querySelector('svg');
            if (!tooltip || !svg) return;

            // A range with more periods than fit is scrolled to the newest end:
            // the left edge of a 90-day daily chart is the least interesting
            // thing on the page.
            if (scroller) scroller.scrollLeft = scroller.scrollWidth;

            function render(hit) {
                let rows;
                try {
                    rows = JSON.parse(hit.dataset.rows || '[]');
                } catch (e) {
                    return;
                }

                tooltip.replaceChildren();

                const title = document.createElement('div');
                title.className = 'viz-tooltip-title';
                // Period labels are formatted server-side, but build the node
                // with textContent regardless — tooltip content is never HTML.
                title.textContent = hit.dataset.title || '';
                tooltip.appendChild(title);

                rows.forEach(function (row) {
                    const line = document.createElement('div');
                    line.className = 'viz-tooltip-row';

                    if (row.color) {
                        const key = document.createElement('span');
                        key.className = 'viz-tooltip-key';
                        key.style.background = row.color;
                        line.appendChild(key);
                    }

                    // Value leads, label follows: the reader already has the
                    // series and wants the number.
                    const value = document.createElement('span');
                    value.className = 'viz-tooltip-value';
                    value.textContent = row.value;
                    line.appendChild(value);

                    const label = document.createElement('span');
                    label.className = 'viz-tooltip-label';
                    label.textContent = row.label;
                    line.appendChild(label);

                    tooltip.appendChild(line);
                });

                tooltip.hidden = false;
            }

            function place(hit) {
                const chartBox = chart.getBoundingClientRect();
                const hitBox = hit.getBoundingClientRect();

                let left = hitBox.left - chartBox.left + hitBox.width / 2 - tooltip.offsetWidth / 2;
                left = Math.max(4, Math.min(left, chartBox.width - tooltip.offsetWidth - 4));

                tooltip.style.left = left + 'px';
                tooltip.style.top = Math.max(4, hitBox.top - chartBox.top - tooltip.offsetHeight - 8) + 'px';
            }

            function show(hit) {
                render(hit);
                place(hit);

                if (crosshair && hit.dataset.crosshair) {
                    crosshair.setAttribute('x1', hit.dataset.crosshair);
                    crosshair.setAttribute('x2', hit.dataset.crosshair);
                    crosshair.setAttribute('opacity', '1');
                }
            }

            function hide() {
                tooltip.hidden = true;
                if (crosshair) crosshair.setAttribute('opacity', '0');
            }

            chart.querySelectorAll('[data-viz-hit]').forEach(function (hit) {
                hit.addEventListener('pointerenter', function () { show(hit); });
                hit.addEventListener('focus', function () { show(hit); });
                hit.addEventListener('pointerleave', hide);
                hit.addEventListener('blur', hide);
            });

            svg.addEventListener('pointerleave', hide);
        });
    </script>
@endsection
