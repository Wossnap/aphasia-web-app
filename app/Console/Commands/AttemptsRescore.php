<?php

namespace App\Console\Commands;

use App\Models\AmharicWord;
use App\Models\Category;
use App\Models\SpeechAttempt;
use App\Services\AttemptRescorer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class AttemptsRescore extends Command
{
    protected $signature = 'attempts:rescore
        {--dry-run : Report what would change without writing anything}
        {--word= : Limit to one word, by id or by the word itself}
        {--category= : Limit to one category, by id, slug or name}
        {--user= : Limit to one user id}
        {--since= : Only attempts from this date onward (Y-m-d)}
        {--allow-downgrade : Also flip correct attempts back to wrong}';

    protected $description = 'Re-score stored attempts against the transliterations each word accepts now, so every Accept applies to the whole history and not just the attempt it was clicked on';

    public function handle(AttemptRescorer $rescorer): int
    {
        $query = SpeechAttempt::query()->whereNotNull('amharic_word_id');

        if ($word = $this->option('word')) {
            $model = ctype_digit((string) $word)
                ? AmharicWord::find((int) $word)
                : AmharicWord::where('word', $word)->first();

            if (!$model) {
                $this->error("No word matching \"{$word}\".");
                return self::FAILURE;
            }

            $this->line("Word: {$model->word} (id {$model->id})");
            $query->where('amharic_word_id', $model->id);
        }

        if ($category = $this->option('category')) {
            $model = Category::query()
                ->where(function ($q) use ($category) {
                    $q->where('slug', $category)->orWhere('name', $category);

                    if (ctype_digit((string) $category)) {
                        $q->orWhere('id', (int) $category);
                    }
                })
                ->first();

            if (!$model) {
                $this->error("No category matching \"{$category}\".");
                return self::FAILURE;
            }

            $this->line("Category: {$model->name} (id {$model->id})");
            $query->whereIn('amharic_word_id', $model->words()->pluck('amharic_words.id'));
        }

        if ($user = $this->option('user')) {
            $query->where('user_id', (int) $user);
        }

        if ($since = $this->option('since')) {
            try {
                $at = Carbon::createFromFormat('Y-m-d', $since)->startOfDay();
            } catch (\Throwable) {
                $this->error("Could not read --since=\"{$since}\"; expected Y-m-d.");
                return self::FAILURE;
            }

            $this->line("Since: {$at->toDateTimeString()}");
            $query->where('created_at', '>=', $at);
        }

        $dryRun = (bool) $this->option('dry-run');
        $allowDowngrade = (bool) $this->option('allow-downgrade');

        $this->line($dryRun ? 'Dry run — nothing will be written.' : 'Applying changes.');
        $this->newLine();

        $report = $rescorer->rescore($query, apply: !$dryRun, allowDowngrade: $allowDowngrade);

        $this->reportOn($report, $dryRun, $allowDowngrade);

        return self::SUCCESS;
    }

    private function reportOn(array $report, bool $dryRun, bool $allowDowngrade): void
    {
        $this->line(sprintf('Scanned %s attempts.', number_format($report['scanned'])));

        $verb = $dryRun ? 'would flip' : 'flipped';
        $this->info(sprintf('%s wrong -> correct: %s', ucfirst($verb), number_format($report['upgraded'])));

        if ($report['downgraded'] > 0) {
            $applied = $allowDowngrade && !$dryRun;
            $this->warn(sprintf(
                'Correct -> wrong: %s%s',
                number_format($report['downgraded']),
                $applied ? ' (applied)' : ' (reported only; pass --allow-downgrade to apply)'
            ));

            foreach (array_slice($report['downgrade_candidates'], 0, 20) as $row) {
                $this->line(sprintf('  attempt #%d  %s  heard "%s"', $row['id'], $row['word'], $row['transcription']));
            }

            $remaining = count($report['downgrade_candidates']) - 20;
            if ($remaining > 0) {
                $this->line(sprintf('  ... and %d more', $remaining));
            }
        }

        $upgradesByWord = array_filter($report['by_word'], fn ($t) => $t['upgraded'] > 0);

        if ($upgradesByWord === []) {
            return;
        }

        uasort($upgradesByWord, fn ($a, $b) => $b['upgraded'] <=> $a['upgraded']);

        $this->newLine();
        $this->line('Upgrades by word:');
        $this->table(
            ['Word', 'Upgraded'],
            array_map(fn ($t) => [$t['word'], number_format($t['upgraded'])], array_slice($upgradesByWord, 0, 30))
        );

        $hidden = count($upgradesByWord) - 30;
        if ($hidden > 0) {
            $this->line(sprintf('... and %d more words', $hidden));
        }
    }
}
