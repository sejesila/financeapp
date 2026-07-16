<?php

namespace App\Console\Commands;

use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MergeCategories extends Command
{
    protected $signature = 'categories:merge {user_id} {--dry-run}';

    protected $description = 'Merge specified expense categories into consolidated categories for a single user';

    /**
     * Each entry: source category names to merge, and the resulting target name.
     * If the target name doesn't already exist, it's created as a child of the
     * same parent as the first matching source category.
     */
    private array $mergeGroups = [
        [
            'sources' => ['Mum', 'Sibling'],
            'target'  => 'Family',
            'icon'    => '👨‍👩‍👧‍👦',
        ],
        [
            'sources' => ['School Fees', 'Books & Supplies'],
            'target'  => 'School Fees & Supplies',
            'icon'    => '🎓',
        ],
        [
            // Target already exists in your category list — will be reused, not recreated.
            'sources' => ['Pharmacy', 'Home Appliances', 'Personal Care', 'Dining Out', 'Home Project', 'Drinking Water'],
            'target'  => 'Other Expenses',
            'icon'    => '🔹',
        ],
    ];

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Running in DRY-RUN mode — no changes will be saved.');
        }

        DB::beginTransaction();

        try {
            foreach ($this->mergeGroups as $group) {
                $this->mergeGroup($userId, $group, $dryRun);
            }

            if ($dryRun) {
                DB::rollBack();
                $this->info('Dry run complete. No changes were saved (rolled back).');
            } else {
                DB::commit();
                $this->info('Merge complete and committed.');
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Merge failed, rolled back: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function mergeGroup(int $userId, array $group, bool $dryRun): void
    {
        $sourceNames = $group['sources'];
        $targetName  = $group['target'];

        $sourceCategories = Category::where('user_id', $userId)
            ->whereIn('name', $sourceNames)
            ->get();

        if ($sourceCategories->isEmpty()) {
            $this->line("Skipping '{$targetName}': no matching source categories found (" . implode(', ', $sourceNames) . ').');
            return;
        }

        $missing = collect($sourceNames)->diff($sourceCategories->pluck('name'));
        if ($missing->isNotEmpty()) {
            $this->warn("'{$targetName}': couldn't find categories: " . $missing->implode(', ') . ' — continuing with what was found.');
        }

        // Find or create the target category.
        $target = Category::where('user_id', $userId)->where('name', $targetName)->first();

        if (!$target) {
            $first = $sourceCategories->first();

            if ($dryRun) {
                $this->info("Would create new category '{$targetName}' (type: {$first->type}, parent_id: " . ($first->parent_id ?? 'null') . ').');
                // Use an in-memory stand-in so the rest of the dry run can report sensibly.
                $target = new Category([
                    'id'        => 0,
                    'user_id'   => $userId,
                    'name'      => $targetName,
                    'type'      => $first->type,
                    'icon'      => $group['icon'],
                    'parent_id' => $first->parent_id,
                ]);
            } else {
                $target = Category::create([
                    'user_id'   => $userId,
                    'name'      => $targetName,
                    'type'      => $first->type,
                    'icon'      => $group['icon'],
                    'parent_id' => $first->parent_id,
                ]);
                $this->info("Created new category '{$targetName}' (id {$target->id}).");
            }
        } else {
            $this->line("Using existing category '{$targetName}' (id {$target->id}).");
        }

        // Source categories to retire (excludes the target itself, in case it was
        // already one of the "source" names, e.g. Other Expenses).
        $sourceIds = $sourceCategories->where('id', '!=', $target->id)->pluck('id')->values();

        if ($sourceIds->isEmpty()) {
            $this->line("'{$targetName}': nothing to merge (target was the only match).");
            return;
        }

        // 1. Move transactions.
        $txCount = Transaction::where('user_id', $userId)
            ->whereIn('category_id', $sourceIds)
            ->count();

        $this->line("'{$targetName}': " . ($dryRun ? 'would move' : 'moving') . " {$txCount} transaction(s) from category id(s) [" . $sourceIds->implode(', ') . "] to '{$targetName}'.");

        if (!$dryRun) {
            Transaction::where('user_id', $userId)
                ->whereIn('category_id', $sourceIds)
                ->update(['category_id' => $target->id]);
        }

        // 2. Merge budgets, summing per year/month since (user_id, category_id, year, month)
        //    must stay unique. Use a separate id list here (source + target) so we don't
        //    accidentally include the target in the deletion step below.
        $idsForBudgetLookup = $sourceIds->concat([$target->id]);

        $budgets = Budget::where('user_id', $userId)
            ->whereIn('category_id', $idsForBudgetLookup)
            ->get()
            ->groupBy(fn($b) => $b->year . '-' . $b->month);

        foreach ($budgets as $key => $rows) {
            [$year, $month] = explode('-', $key);
            $summed = (float) $rows->sum('amount');

            $this->line("'{$targetName}': {$year}-{$month} budget -> {$summed} (combined from " . $rows->count() . ' row(s)).');

            if (!$dryRun) {
                Budget::updateOrCreate(
                    ['user_id' => $userId, 'category_id' => $target->id, 'year' => $year, 'month' => $month],
                    ['amount' => $summed]
                );

                Budget::where('user_id', $userId)
                    ->whereIn('category_id', $sourceIds)
                    ->where('year', $year)
                    ->where('month', $month)
                    ->delete();
            }
        }

        // 3. Delete the now-empty source categories.
        if (!$dryRun) {
            Category::where('user_id', $userId)->whereIn('id', $sourceIds)->delete();
            $this->info("'{$targetName}': deleted " . $sourceIds->count() . ' merged source categor' . ($sourceIds->count() === 1 ? 'y' : 'ies') . '.');
        } else {
            $this->line("'{$targetName}': would delete " . $sourceIds->count() . ' source categor' . ($sourceIds->count() === 1 ? 'y' : 'ies') . ' [' . $sourceIds->implode(', ') . '].');
        }
    }
}
