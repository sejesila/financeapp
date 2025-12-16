<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResourceOwnership
{
    /**
     * Handle an incoming request.
     *
     * This middleware provides an additional layer of security by ensuring
     * that resource IDs in query parameters also belong to the authenticated user.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if loan_id is in the request
        if ($request->has('loan_id')) {
            $loan = \App\Models\Loan::find($request->input('loan_id'));

            if ($loan && $loan->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to resource.');
            }
        }

        // Check if account_id is in the request
        if ($request->has('account_id')) {
            $account = \App\Models\Account::find($request->input('account_id'));

            if ($account && $account->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to resource.');
            }
        }

        // Check if transaction_id is in the request
        if ($request->has('transaction_id')) {
            $transaction = \App\Models\Transaction::find($request->input('transaction_id'));

            if ($transaction && $transaction->user_id !== auth()->id()) {
                abort(403, 'Unauthorized access to resource.');
            }
        }

        return $next($request);
    }
}
