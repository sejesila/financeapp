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

        // Default $from to the account creation date — full history from day one.
        $from = $request->input('from')
            ? Carbon::parse($request->input('from'))->startOfDay()
            : Carbon::parse($account->created_at)->startOfDay();

        $to = $request->input('to')
            ? Carbon::parse($request->input('to'))->endOfDay()
            : now()->endOfDay();

        $statementData = $this->statementService->buildStatementData($account, $from, $to);

        $viewData = array_merge($statementData, [
            'account' => $account,
            'user'    => auth()->user(),
        ]);

        // ── PDF download mode ─────────────────────────────────────────────────
        if ($request->boolean('download')) {
            $filename = $account->name
                . '_Statement_'
                . $from->format('Y-m-d')
                . '_to_'
                . $to->format('Y-m-d')
                . '.pdf';

            return Pdf::view('accounts.statement', $viewData)
                ->format('a4')
                ->download($filename);
        }

        return view('accounts.statement', $viewData);
    }
}
