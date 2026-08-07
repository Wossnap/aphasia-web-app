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
     * Past this many buckets the columns are thinner than a readable label
     * even with horizontal scrolling, so we stop generating and tell the
     * admin to pick a coarser granularity rather than render a smear.
     */
    private const MAX_BUCKETS = 400;

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

        $attempts = SpeechAttempt::query()
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->whereBetween('created_at', [$start, $end])
            ->get(['created_at', 'is_correct', 'user_id']);

        $byKey = [];
        foreach ($buckets as $i => $bucket) {
            $byKey[$bucket['key']] = $i;
        }

        foreach ($attempts as $attempt) {
            $key = $this->periodStart(CarbonImmutable::parse($attempt->created_at), $granularity)->toDateString();
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
        ]);
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

        $today = CarbonImmutable::today();

        if ($range === 'custom') {
            $start = $this->parseDate($request->query('from')) ?? $today->subDays(29);
            $end = $this->parseDate($request->query('to')) ?? $today;
        } elseif ($range === 'all') {
            $earliest = SpeechAttempt::query()
                ->when($userId, fn ($q) => $q->where('user_id', $userId))
                ->min('created_at');
            $start = $earliest ? CarbonImmutable::parse($earliest) : $today;
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
            return CarbonImmutable::createFromFormat('Y-m-d', $value)->startOfDay();
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
