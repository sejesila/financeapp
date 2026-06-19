<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\StatementDataService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Spatie\LaravelPdf\Facades\Pdf;

class StatementController extends Controller
{
    public function __construct(private StatementDataService $statementService) {}

    // =========================================================================
    // MONTHLY / DATE-RANGE STATEMENT
    // =========================================================================

    public function show(Request $request, Account $account)
    {
        if ($account->user_id !== Auth::id()) {
            abort(403);
        }

        if ($account->type !== 'savings') {
            return redirect()->route('accounts.show', $account)
                ->with('error', 'Statements are only available for savings accounts.');
        }

        // Default to current month; allow ?month=2026-05 to navigate
        $monthInput = $request->input('month', now()->format('Y-m'));
        $monthStart = Carbon::parse($monthInput . '-01')->startOfDay();
        $monthEnd   = $monthStart->copy()->endOfMonth()->endOfDay();

        // Clamp: don't go before account creation or after today
        $from = $monthStart->lt(Carbon::parse($account->created_at))
            ? Carbon::parse($account->created_at)->startOfDay()
            : $monthStart;
        $to = $monthEnd->gt(now()) ? now()->endOfDay() : $monthEnd;

        $statementData = $this->statementService->buildStatementData($account, $from, $to);

        // Build list of available months (from account creation to now)
        $accountCreated = Carbon::parse($account->created_at)->startOfMonth();
        $months = [];
        $cursor = now()->startOfMonth();
        while ($cursor->gte($accountCreated)) {
            $months[] = $cursor->format('Y-m');
            $cursor->subMonth();
        }

        $viewData = array_merge($statementData, [
            'account'        => $account,
            'user'           => auth()->user(),
            'selectedMonth'  => $monthStart->format('Y-m'),
            'selectedLabel'  => $monthStart->format('F Y'),
            'prevMonth'      => $monthStart->copy()->subMonth()->format('Y-m'),
            'nextMonth'      => $monthStart->copy()->addMonth()->format('Y-m'),
            'hasPrev'        => $monthStart->copy()->subMonth()->gte($accountCreated),
            'hasNext'        => $monthStart->copy()->addMonth()->lte(now()->startOfMonth()),
            'availableMonths'=> $months,
        ]);

        // ── PDF download mode ─────────────────────────────────────────────────
        if ($request->boolean('download')) {
            $filename = $account->name
                . '_Statement_'
                . $monthStart->format('Y-m')
                . '.pdf';

            return Pdf::view('accounts.statement', $viewData)
                ->format('a4')
                ->download($filename);
        }

        return view('accounts.statement', $viewData);
    }
}
