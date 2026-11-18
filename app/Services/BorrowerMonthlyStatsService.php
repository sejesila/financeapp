<?php

namespace App\Services;

use App\Models\LoanGiven;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class BorrowerMonthlyStatsService
{
    /** Last $months months, oldest first. "New" is judged against ALL history, not just the window. */
    public function compute(int $months = 12): array
    {
        $loans = LoanGiven::where('user_id', Auth::id())
            ->whereNotNull('borrower_id')
            ->whereNotNull('disbursed_date')
            ->get();

        $key = fn ($d) => Carbon::parse($d)->format('Y-m');

        // Each borrower's first-ever loan month.
        $firstMonth = $loans->groupBy('borrower_id')
            ->map(fn ($g) => $key($g->min('disbursed_date')));

        $byMonth = $loans->groupBy(fn ($l) => $key($l->disbursed_date));

        $start = now()->startOfMonth()->subMonths($months - 1);
        $rows = [];
        $prevBorrowers = collect();

        // Seed "previous month" so the first row's retention is meaningful.
        $seedKey = $start->copy()->subMonth()->format('Y-m');
        if (isset($byMonth[$seedKey])) {
            $prevBorrowers = $byMonth[$seedKey]->pluck('borrower_id')->unique();
        }

        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i);
            $k = $m->format('Y-m');
            $monthLoans = $byMonth[$k] ?? collect();

            $ids = $monthLoans->pluck('borrower_id')->unique();
            $newIds = $ids->filter(fn ($id) => $firstMonth[$id] === $k);
            $returningIds = $ids->diff($newIds);

            $disbursed = fn ($set) => (float) $monthLoans
                ->whereIn('borrower_id', $set->all())
                ->sum(fn ($l) => $l->original_principal);

            $retained = $prevBorrowers->intersect($ids)->count();

            $rows[] = [
                'label'               => $m->format('M Y'),
                'new'                 => $newIds->count(),
                'returning'           => $returningIds->count(),
                'active'              => $ids->count(),
                'loans'               => $monthLoans->count(),
                'disbursed_new'       => $disbursed($newIds),
                'disbursed_returning' => $disbursed($returningIds),
                'returning_share'     => $ids->count() ? round($returningIds->count() / $ids->count() * 100, 1) : null,
                // Of last month's borrowers, how many borrowed again this month.
                'retention'           => $prevBorrowers->count() ? round($retained / $prevBorrowers->count() * 100, 1) : null,
                'retained'            => $retained,
            ];

            $prevBorrowers = $ids;
        }

        $all = collect($rows);

        return [
            'rows' => $rows,
            'totals' => [
                'new'              => $all->sum('new'),
                'returning'        => $all->sum('returning'),
                'total_borrowers'  => $firstMonth->count(),
                'repeat_borrowers' => $loans->groupBy('borrower_id')->filter(fn ($g) => $g->count() > 1)->count(),
                'avg_new_per_month' => round($all->avg('new'), 1),
            ],
        ];
    }
}
