<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Referrer extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'contact', 'default_share_percentage', 'is_active','default_interest_rate',
    ];

    protected $casts = [
        'default_share_percentage' => 'decimal:2',
        'is_active'                => 'boolean',
        'default_interest_rate' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::addGlobalScope('ownedByUser', function ($builder) {
            if (Auth::check()) {
                $builder->where('user_id', Auth::id());
            }
        });
    }

    public function loans()
    {
        return $this->hasMany(LoanGiven::class);
    }

    public function payouts()
    {
        return $this->hasMany(ReferrerPayout::class);
    }

    /**
     * The dedicated referrer_float account this referrer's collected money
     * (principal + interest) lands in, if one's been set up. A referrer can
     * have at most one — if you find yourself needing more than one, that's
     * really two different referrers.
     */
    public function floatAccount()
    {
        return $this->hasOne(Account::class)->where('type', 'referrer_float');
    }

    /**
     * Interest earned on her referred loans since the last payout (or ever,
     * if she's never been paid), based on closed loans only.
     */
    public function getUnpaidInterestAttribute()
    {
        return $this->loans()
            ->where('status', 'paid')
            ->whereNull('referrer_payout_id')
            ->where('referrer_deducted_before_deposit', false)
            ->sum('interest_amount');
    }

    /**
     * Per-loan interest that has been recognized (via a rollover payment or
     * final closure) into this referrer's float account, but not yet
     * reconciled out via a ReferrerFloatReconciliation row. Returns
     * [loan_given_id => pending_amount], loans with nothing pending omitted.
     *
     * This is unrelated to getUnpaidInterestAttribute()/payouts() above —
     * those track the referrer's OWN commission on interest. This tracks
     * YOUR money (principal + interest) that she's collected on your behalf
     * and is holding, still owed back to you.
     *
     * Deliberately not filtered by loan status: a loan that has already
     * closed can still have interest stuck in the float from an earlier
     * rollover payment, if the final closing interest happened to route
     * elsewhere — that money is still pending regardless of the loan's
     * current status.
     */
    public function pendingFloatInterestByLoan(): \Illuminate\Support\Collection
    {
        $floatAccount = $this->floatAccount;

        if (!$floatAccount) {
            return collect();
        }

        $earned = DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->join('loan_given_payments', 'transactions.reference_id', '=', 'loan_given_payments.id')
            ->join('loans_given', 'loan_given_payments.loan_given_id', '=', 'loans_given.id')
            ->where('loans_given.referrer_id', $this->id)
            ->where('transactions.account_id', $floatAccount->id)
            ->where('categories.name', 'Loan Interest')
            ->whereNull('transactions.deleted_at')
            ->selectRaw('loans_given.id as loan_id, SUM(transactions.amount) as total')
            ->groupBy('loans_given.id')
            ->get()
            ->pluck('total', 'loan_id');

        if ($earned->isEmpty()) {
            return collect();
        }

        $reconciled = DB::table('referrer_float_reconciliations')
            ->join('loans_given', 'referrer_float_reconciliations.loan_given_id', '=', 'loans_given.id')
            ->where('loans_given.referrer_id', $this->id)
            ->selectRaw('loans_given.id as loan_id, SUM(referrer_float_reconciliations.amount) as total')
            ->groupBy('loans_given.id')
            ->get()
            ->pluck('total', 'loan_id');

        $pending = collect();

        foreach ($earned as $loanId => $earnedTotal) {
            $remaining = round((float) $earnedTotal - (float) ($reconciled[$loanId] ?? 0), 2);
            if ($remaining > 0.01) {
                $pending->put($loanId, $remaining);
            }
        }

        return $pending;
    }
}
