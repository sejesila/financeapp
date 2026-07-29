<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\Referrer;
use App\Models\ReferrerPayout;
use App\Models\Transaction;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReferrerPayoutController extends Controller
{
    public function create(Referrer $referrer)
    {
        $this->authorize('view', $referrer);

        $unpaidLoans = $referrer->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->orderBy('repaid_date')
            ->get();

        $totalInterest = $unpaidLoans->sum('interest_amount');

        $accounts = Account::where('user_id', Auth::id())
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return view('referrer-payouts.create', compact('referrer', 'unpaidLoans', 'totalInterest', 'accounts'));
    }

    public function store(Request $request, Referrer $referrer)
    {
        $this->authorize('view', $referrer);

        $validated = $request->validate([
            'period_start'      => 'required|date',
            'period_end'        => 'required|date|after_or_equal:period_start',
            'share_percentage'  => 'required|numeric|min:0|max:100',
            'account_id'        => 'required|exists:accounts,id',
            'paid_date'         => 'required|date',
        ]);

        $account = Account::where('user_id', Auth::id())->findOrFail($validated['account_id']);

        $loans = $referrer->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->whereBetween('repaid_date', [$validated['period_start'], $validated['period_end']])
            ->get();

        if ($loans->isEmpty()) {
            return back()->with('error', 'No unpaid referred loans found in that period.');
        }

        $totalInterest = $loans->sum('interest_amount');
        $amountPaid    = round($totalInterest * ($validated['share_percentage'] / 100), 2);

        if ($amountPaid <= 0) {
            return back()->with('error', 'Computed payout amount is zero — nothing to pay.');
        }

        if ($account->current_balance < $amountPaid) {
            return back()->with('error', "Insufficient balance in {$account->name} to pay out KES " . number_format($amountPaid, 0));
        }

        DB::beginTransaction();

        try {
            $commissionCategory = Category::where('user_id', Auth::id())
                ->where('name', 'Referrer Commission')
                ->first() ?: Category::create([
                'user_id' => Auth::id(), 'parent_id' => null,
                'name' => 'Referrer Commission', 'type' => 'expense', 'is_active' => true,
            ]);

            $transaction = Transaction::create([
                'user_id'     => Auth::id(),
                'account_id'  => $account->id,
                'category_id' => $commissionCategory->id,
                'type'        => 'expense',
                'description' => "Referrer commission to {$referrer->name} ({$validated['period_start']} to {$validated['period_end']})",
                'amount'      => $amountPaid,
                'date'        => $validated['paid_date'],
            ]);

            $payout = ReferrerPayout::create([
                'user_id'          => Auth::id(),
                'referrer_id'      => $referrer->id,
                'period_start'     => $validated['period_start'],
                'period_end'       => $validated['period_end'],
                'total_interest'   => $totalInterest,
                'share_percentage' => $validated['share_percentage'],
                'amount_paid'      => $amountPaid,
                'account_id'       => $account->id,
                'transaction_id'   => $transaction->id,
                'paid_date'        => $validated['paid_date'],
            ]);

           // $loans()->each(fn () => null); // n/a — kept for clarity, see line below
            $loans->each(function ($loan) use ($payout) {
                $loan->referrer_payout_id = $payout->id;
                $loan->save();
            });

            DB::commit();
            $account->updateBalance();

            return redirect()->route('referrers.show', $referrer)
                ->with('success', "Paid KES " . number_format($amountPaid, 0) . " to {$referrer->name} for {$loans->count()} loan(s).");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('ReferrerPayoutController@store failed', ['referrer_id' => $referrer->id, 'error' => $e->getMessage()]);
            return back()->with('error', 'Payout failed: ' . $e->getMessage());
        }
    }
}
