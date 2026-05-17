<?php

namespace App\Console\Commands;

use App\Models\Account;
use App\Models\Transaction;
use App\Services\InterestService;
use Illuminate\Console\Command;

class BackfillInterestRates extends Command
{
    protected $signature   = 'interest:backfill {--dry-run : Preview without saving}';
    protected $description = 'Backfill computed_rate on existing interest transactions that have a null rate.';

    public function __construct(private readonly InterestService $interestService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        // Find all interest transactions with no computed_rate
        $transactions = Transaction::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereNull('computed_rate')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.name', 'Interest')
            ->select('transactions.*')
            ->orderBy('transactions.date')
            ->get();

        if ($transactions->isEmpty()) {
            $this->info('No interest transactions with a null computed_rate found.');
            return self::SUCCESS;
        }

        $this->info("Found {$transactions->count()} interest transaction(s) to backfill.");
        $this->newLine();

        $updated = 0;
        $skipped = 0;

        foreach ($transactions as $txn) {
            $account = Account::withoutGlobalScopes()->find($txn->account_id);
            if (!$account) {
                $this->warn("  [SKIP] Transaction #{$txn->id} — account not found.");
                $skipped++;
                continue;
            }

            $rate = $this->interestService->computeDailyRate($account, $txn);

            if ($rate === null) {
                $this->warn("  [SKIP] Transaction #{$txn->id} ({$txn->date}) KES " .
                    number_format($txn->amount, 0) . " on '{$account->name}' — zero/negative opening balance.");
                $skipped++;
                continue;
            }

            $apy = round($rate * 365, 2);

            $this->line("  [SET]  Transaction #{$txn->id} ({$txn->date}) KES " .
                number_format($txn->amount, 0) .
                " on '{$account->name}' → {$rate}%/day ({$apy}% APY)");

            if (!$dryRun) {
                $txn->update(['computed_rate' => $rate]);
            }

            $updated++;
        }

        $this->newLine();

        if ($dryRun) {
            $this->warn("DRY RUN — no changes saved. Remove --dry-run to apply.");
        } else {
            $this->info("Done. Updated: {$updated}, Skipped: {$skipped}.");
        }

        return self::SUCCESS;
    }
}
