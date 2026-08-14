<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SpeechAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

/**
 * Attempt volume over time for one user (or everyone), bucketed daily /
 * weekly / monthly / yearly over a preset or custom date range.
 *
 * Bucketing is done in PHP rather than with SQL date functions so the same
 * code runs on MySQL (app) and SQLite (tests) — the query only ever pulls
 * two columns for one date range, so the cost is in the row count, not the
 * grouping.
 */
class AttemptAnalyticsController extends Controller
{
    /** Range presets offered by the filter row, shortest-first. */
    public const RANGES = [
        '7d' => 'Last 7 days',
        '30d' => 'Last 30 days',
        '90d' => 'Last 90 days',
        '12m' => 'Last 12 months',
        'ytd' => 'This year',
        'all' => 'All time',
        'custom' => 'Custom range',
    ];

    public const GRANULARITIES = [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'yearly' => 'Yearly',
    ];

    /**
     * Pause lengths offered for splitting a day's attempts into practice
     * blocks. Deliberately short options: the point is to separate "picked
     * the tablet up again after lunch" from "kept going", and an hour is
     * already long enough to swallow that distinction.
     */
    public const BLOCK_GAPS = [
        15 => '15 minutes',
        30 => '30 minutes',
        60 => '1 hour',
    ];

    /**
     * Past this many buckets the columns are thinner than a readable label
     * even with horizontal scrolling, so we stop generating and tell the
     * admin to pick a coarser granularity rather than render a smear.
     */
    private const MAX_BUCKETS = 400;

    /**
     * Timeline rows are a fixed height each and stack vertically, so past
     * this many the section stops being a chart and becomes a scroll. The
     * most recent rows win — they're the ones being asked about.
     */
    private const MAX_BLOCK_ROWS = 30;

    /** The timeline never squeezes below this many hours of clock width. */
    private const MIN_AXIS_MINUTES = 360;

    public function index(Request $request)
    {
        $userId = $request->query('user_id') ?: null;
        $granularity = $request->query('granularity');
        if (!isset(self::GRANULARITIES[$granularity])) {
            $granularity = 'daily';
        }

        [$start, $end, $range] = $this->resolveRange($request, $userId);

        $buckets = $this->makeBuckets($start, $end, $granularity);
        $truncated = count($buckets) > self::MAX_BUCKETS;
        if ($truncated) {
            $buckets = array_slice($buckets, -self::MAX_BUCKETS);
            $start = $buckets[0]['from'];
        }

        // The range is reasoned about in the viewer's zone but compared against
        // storage in the application's, so it converts on the way into the
        // query and nowhere else.
        $attempts = SpeechAttempt::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [
                $start->setTimezone(config('app.timezone')),
                $end->setTimezone(config('app.timezone')),
            ])
            ->get(['created_at', 'is_correct', 'user_id']);

        $byKey = [];
        foreach ($buckets as $i => $bucket) {
            $byKey[$bucket['key']] = $i;
        }

        foreach ($attempts as $attempt) {
            $key = $this->periodStart($this->inDisplayZone($attempt->created_at), $granularity)->toDateString();
            if (!isset($byKey[$key])) {
                continue;
            }
            $i = $byKey[$key];
            $buckets[$i]['attempts']++;
            $buckets[$i][$attempt->is_correct ? 'correct' : 'incorrect']++;
        }

        // Accuracy is undefined with zero attempts — leave it null so the line
        // chart breaks there instead of drawing a misleading 0%.
        foreach ($buckets as $i => $bucket) {
            $buckets[$i]['accuracy'] = $bucket['attempts'] > 0
                ? (int) round($bucket['correct'] / $bucket['attempts'] * 100)
                : null;
        }

        $stats = $this->summarize($buckets);

        // Only meaningful when looking across everyone; with a user selected
        // it would be a one-bar chart of the number already in the tiles.
        $topUsers = $userId ? collect() : $this->topUsers($attempts);

        $users = User::orderBy('name')->get(['id', 'name', 'email']);
        $selectedUser = $userId ? $users->firstWhere('id', (int) $userId) : null;

        // Same rows again, cut a different way: the charts above answer "how
        // much on this day", the timeline answers "when during it".
        $gap = $this->resolveGap($request);
        $blocks = $this->practiceBlocks($attempts, $gap, $users);

        return view('admin.analytics.index', [
            'buckets' => $buckets,
            'stats' => $stats,
            'topUsers' => $topUsers,
            'users' => $users,
            'selectedUser' => $selectedUser,
            'userId' => $userId,
            'granularity' => $granularity,
            'range' => $range,
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'truncated' => $truncated,
            'gap' => $gap,
            'blockRows' => $blocks['rows'],
            'blockStats' => $blocks['stats'],
            'blockAxis' => $blocks['axis'],
            'blockRowsTruncated' => $blocks['truncated'],
            'blockShowsUser' => $userId === null,
        ]);
    }

    /**
     * The zone times are shown in — the viewer's own, resolved per request by
     * SetDisplayTimezone. Every "which day" and "what time of day" decision in
     * here runs through this, since those answers change with the zone.
     */
    private function displayTz(): string
    {
        return config('app.display_timezone') ?: config('app.timezone');
    }

    /**
     * A stored timestamp as an immutable instant in the display zone. Always
     * copies: the model's own Carbon is mutable and shared, so calling
     * setTimezone on it directly would corrupt it for later passes.
     */
    private function inDisplayZone($value): CarbonImmutable
    {
        return CarbonImmutable::parse($value)->setTimezone($this->displayTz());
    }

    /** Gap preset in minutes, falling back to the configured default. */
    private function resolveGap(Request $request): int
    {
        $gap = (int) $request->query('gap');
        if (isset(self::BLOCK_GAPS[$gap])) {
            return $gap;
        }

        $configured = (int) config('services.analytics.block_gap_minutes', 30);

        return isset(self::BLOCK_GAPS[$configured]) ? $configured : 30;
    }

    /**
     * Split each person's day of attempts into practice blocks: consecutive
     * attempts belong to the same block until a pause longer than the gap,
     * which starts a new one.
     *
     * Grouped by (day, user) rather than day alone so that an unfiltered view
     * never welds two people's attempts into one imaginary block — with a user
     * selected it collapses to one row per day. Attempts with no user are
     * skipped: separate guests are indistinguishable, so any block built from
     * them would be fiction.
     *
     * A block that runs past midnight is cut at the day boundary, since the
     * timeline's whole premise is a row per calendar day.
     */
    private function practiceBlocks($attempts, int $gapMinutes, $users): array
    {
        $usersById = $users->keyBy('id');
        $gapSeconds = $gapMinutes * 60;

        // Grouped on the date *in the viewer's zone*: a 1am sitting in Addis
        // is the previous day in UTC, and putting it on the wrong row is not
        // something the front end could correct afterwards.
        $groups = $attempts
            ->filter(fn ($a) => $a->user_id !== null)
            ->sortBy(fn ($a) => $a->created_at->getTimestamp())
            ->groupBy(fn ($a) => $this->inDisplayZone($a->created_at)->toDateString() . '|' . $a->user_id);

        $rows = [];
        foreach ($groups as $key => $groupAttempts) {
            [$date, $groupUserId] = explode('|', $key);

            $blocks = [];
            $current = null;
            foreach ($groupAttempts as $attempt) {
                $at = $this->inDisplayZone($attempt->created_at);

                // Compare on raw timestamps: diffInMinutes changed its sign
                // convention between Carbon majors, and this cannot be wrong.
                if ($current !== null && $at->getTimestamp() - $current['end']->getTimestamp() > $gapSeconds) {
                    $blocks[] = $current;
                    $current = null;
                }

                $current ??= ['start' => $at, 'end' => $at, 'attempts' => 0, 'correct' => 0];
                $current['end'] = $at;
                $current['attempts']++;
                $current['correct'] += $attempt->is_correct ? 1 : 0;
            }
            if ($current !== null) {
                $blocks[] = $current;
            }

            $rows[] = [
                'date' => CarbonImmutable::parse($date, $this->displayTz()),
                'user' => $usersById->get((int) $groupUserId),
                'blocks' => array_map(fn ($b) => $this->finishBlock($b), $blocks),
            ];
        }

        // Newest first: a caregiver opens this to see how the last few days
        // went, not how the range started.
        usort($rows, function ($a, $b) {
            return [$b['date']->getTimestamp(), $a['user']?->name ?? '']
                <=> [$a['date']->getTimestamp(), $b['user']?->name ?? ''];
        });

        $truncated = max(0, count($rows) - self::MAX_BLOCK_ROWS);
        $rows = array_slice($rows, 0, self::MAX_BLOCK_ROWS);

        foreach ($rows as $i => $row) {
            $rows[$i]['attempts'] = array_sum(array_column($row['blocks'], 'attempts'));
            $rows[$i]['minutes'] = array_sum(array_column($row['blocks'], 'minutes'));
        }

        return [
            'rows' => $rows,
            'axis' => $this->blockAxis($rows),
            'stats' => $this->summarizeBlocks($rows),
            'truncated' => $truncated,
        ];
    }

    /** Derive a block's reported shape once its last attempt is known. */
    private function finishBlock(array $block): array
    {
        $minutes = ($block['end']->getTimestamp() - $block['start']->getTimestamp()) / 60;

        return [
            'start' => $block['start'],
            'end' => $block['end'],
            // First attempt to last, so a block of one attempt is 0 minutes
            // rather than a made-up duration.
            'minutes' => round($minutes, 1),
            'start_minute' => $block['start']->hour * 60 + $block['start']->minute,
            'end_minute' => $block['end']->hour * 60 + $block['end']->minute,
            'attempts' => $block['attempts'],
            'correct' => $block['correct'],
            'incorrect' => $block['attempts'] - $block['correct'],
            'accuracy' => $block['attempts'] > 0
                ? (int) round($block['correct'] / $block['attempts'] * 100)
                : null,
        ];
    }

    /**
     * Clip the clock axis to the hours actually practised, padded out to a
     * whole hour each side — a full midnight-to-midnight axis would spend
     * most of its width on hours nobody has ever practised in.
     */
    private function blockAxis(array $rows): array
    {
        $starts = [];
        $ends = [];
        foreach ($rows as $row) {
            foreach ($row['blocks'] as $block) {
                $starts[] = $block['start_minute'];
                $ends[] = $block['end_minute'];
            }
        }

        if ($starts === []) {
            return ['start' => 8 * 60, 'end' => 20 * 60];
        }

        $start = (int) (floor(min($starts) / 60) * 60);
        $end = (int) (ceil(max($ends) / 60) * 60);

        // Grow symmetrically to the minimum span, then shove the window back
        // inside the day if either edge fell out of it.
        if ($end - $start < self::MIN_AXIS_MINUTES) {
            $needed = self::MIN_AXIS_MINUTES - ($end - $start);
            $start -= (int) (floor($needed / 120) * 60);
            $end = $start + self::MIN_AXIS_MINUTES;
        }
        if ($start < 0) {
            $end -= $start;
            $start = 0;
        }
        if ($end > 1440) {
            $start = max(0, $start - ($end - 1440));
            $end = 1440;
        }

        return ['start' => $start, 'end' => $end];
    }

    private function summarizeBlocks(array $rows): array
    {
        $blocks = [];
        foreach ($rows as $row) {
            foreach ($row['blocks'] as $block) {
                $blocks[] = $block + ['date' => $row['date'], 'user' => $row['user']];
            }
        }

        $longest = null;
        foreach ($blocks as $block) {
            if (!$longest || $block['minutes'] > $longest['minutes']) {
                $longest = $block;
            }
        }

        $days = count(array_unique(array_map(fn ($r) => $r['date']->toDateString(), $rows)));

        return [
            'blocks' => count($blocks),
            'days' => $days,
            'avg_per_day' => $days > 0 ? round(count($blocks) / $days, 1) : 0,
            'avg_minutes' => $blocks !== [] ? round(array_sum(array_column($blocks, 'minutes')) / count($blocks)) : 0,
            'total_minutes' => round(array_sum(array_column($blocks, 'minutes'))),
            'longest' => $longest,
        ];
    }

    /**
     * Turn the range preset (or the custom from/to pair) into a concrete
     * start/end. Returns the resolved preset too, since an unknown or
     * unusable one falls back to the 30-day default.
     */
    private function resolveRange(Request $request, ?string $userId): array
    {
        $range = $request->query('range', '30d');
        if (!isset(self::RANGES[$range])) {
            $range = '30d';
        }

        // "Today" is the viewer's today, not the server's — near midnight the
        // two are different days.
        $today = CarbonImmutable::today($this->displayTz());

        if ($range === 'custom') {
            $start = $this->parseDate($request->query('from')) ?? $today->subDays(29);
            $end = $this->parseDate($request->query('to')) ?? $today;
        } elseif ($range === 'all') {
            $earliest = SpeechAttempt::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->min('created_at');
            $start = $earliest ? $this->inDisplayZone($earliest) : $today;
            $end = $today;
        } else {
            $end = $today;
            $start = match ($range) {
                '7d' => $today->subDays(6),
                '90d' => $today->subDays(89),
                '12m' => $today->subMonths(11)->startOfMonth(),
                'ytd' => $today->startOfYear(),
                default => $today->subDays(29),
            };
        }

        if ($start->greaterThan($end)) {
            [$start, $end] = [$end, $start];
        }

        return [$start->startOfDay(), $end->endOfDay(), $range];
    }

    private function parseDate(?string $value): ?CarbonImmutable
    {
        if (!$value) {
            return null;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $value, $this->displayTz())->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * One entry per period in the range, including periods with no attempts —
     * a gap in practice is exactly what the admin needs to see, so empty
     * buckets are kept rather than skipped.
     *
     * Periods at the edges are clipped to the requested range instead of
     * snapping outward, so "last 30 days" never quietly includes a whole
     * extra month. Each bucket carries its real from/to for the tooltip.
     */
    private function makeBuckets(CarbonImmutable $start, CarbonImmutable $end, string $granularity): array
    {
        $buckets = [];
        $cursor = $this->periodStart($start, $granularity);

        while ($cursor->lessThanOrEqualTo($end)) {
            $periodEnd = $this->periodEnd($cursor, $granularity);
            $from = $cursor->lessThan($start) ? $start : $cursor;
            $to = $periodEnd->greaterThan($end) ? $end : $periodEnd;

            $buckets[] = [
                'key' => $cursor->toDateString(),
                // Labelled from the clipped start, not the period start, so a
                // first bucket that the range cuts into doesn't advertise a
                // date the chart isn't actually counting from.
                'label' => $this->axisLabel($cursor, $from, $granularity),
                'full_label' => $this->fullLabel($cursor, $from, $to, $granularity),
                'from' => $from,
                'to' => $to,
                'attempts' => 0,
                'correct' => 0,
                'incorrect' => 0,
            ];

            $cursor = $this->periodStart($periodEnd->addDay(), $granularity);
        }

        return $buckets;
    }

    private function periodStart(CarbonImmutable $date, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'weekly' => $date->startOfWeek(),
            'monthly' => $date->startOfMonth(),
            'yearly' => $date->startOfYear(),
            default => $date->startOfDay(),
        };
    }

    private function periodEnd(CarbonImmutable $periodStart, string $granularity): CarbonImmutable
    {
        return match ($granularity) {
            'weekly' => $periodStart->endOfWeek(),
            'monthly' => $periodStart->endOfMonth(),
            'yearly' => $periodStart->endOfYear(),
            default => $periodStart->endOfDay(),
        };
    }

    private function axisLabel(CarbonImmutable $periodStart, CarbonImmutable $from, string $granularity): string
    {
        return match ($granularity) {
            'monthly' => $periodStart->format('M Y'),
            'yearly' => $periodStart->format('Y'),
            default => $from->format('M j'),
        };
    }

    /** The tooltip/table label — spells out the period's real window. */
    private function fullLabel(CarbonImmutable $periodStart, CarbonImmutable $from, CarbonImmutable $to, string $granularity): string
    {
        return match ($granularity) {
            'weekly' => $from->format('M j') . ' – ' . $to->format('M j, Y'),
            'monthly' => $periodStart->format('F Y'),
            'yearly' => $periodStart->format('Y'),
            default => $periodStart->format('D, M j, Y'),
        };
    }

    private function summarize(array $buckets): array
    {
        $attempts = array_sum(array_column($buckets, 'attempts'));
        $correct = array_sum(array_column($buckets, 'correct'));
        $active = array_values(array_filter($buckets, fn ($b) => $b['attempts'] > 0));

        $busiest = null;
        foreach ($active as $bucket) {
            if (!$busiest || $bucket['attempts'] > $busiest['attempts']) {
                $busiest = $bucket;
            }
        }

        return [
            'attempts' => $attempts,
            'correct' => $correct,
            'incorrect' => $attempts - $correct,
            'accuracy' => $attempts > 0 ? (int) round($correct / $attempts * 100) : null,
            'active_periods' => count($active),
            'total_periods' => count($buckets),
            'avg_per_active' => count($active) > 0 ? round($attempts / count($active), 1) : 0,
            'busiest' => $busiest,
        ];
    }

    /**
     * Most active users in the range, so an admin scanning "everyone" can see
     * who to drill into. Capped at 8 — past that the list stops being a
     * shortlist and the user picker is the better tool.
     *
     * Needs at least two users to be a comparison; with one it would be a
     * one-bar chart of the number already in the tiles above.
     */
    private function topUsers($attempts)
    {
        $counts = $attempts->whereNotNull('user_id')->countBy('user_id');
        if ($counts->count() < 2) {
            return collect();
        }

        $users = User::whereIn('id', $counts->keys())->get(['id', 'name', 'email'])->keyBy('id');

        return $counts
            ->sortDesc()
            ->take(8)
            ->map(fn ($count, $id) => ['user' => $users->get($id), 'attempts' => $count])
            ->filter(fn ($row) => $row['user'] !== null)
            ->values();
    }
}
