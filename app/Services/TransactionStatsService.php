<?php

namespace App\Services;

use App\Models\Transaction;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TransactionStatsService
{
    private const EXCLUDED_SUMMARY_CATEGORIES = [
        'Loan Disbursement', 'Loan Receipt', 'Balance Adjustment', 'Client Funds',
    ];

    // ── public API ────────────────────────────────────────────────────────────

    public function totals(): array
    {
        $base = Transaction::where('user_id', Auth::id())->where('is_transaction_fee', false);

        return [
            'totalThisWeek'  => (clone $base)->whereBetween('date', [now()->startOfWeek(), now()->endOfWeek()])->sum('amount'),
            'totalLastWeek'  => (clone $base)->whereBetween('date', [now()->subWeek()->startOfWeek(), now()->subWeek()->endOfWeek()])->sum('amount'),
            'totalThisMonth' => (clone $base)->whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount'),
            'totalLastMonth' => (clone $base)->whereMonth('date', now()->subMonth()->month)->whereYear('date', now()->subMonth()->year)->sum('amount'),
            'totalThisYear'  => (clone $base)->whereYear('date', now()->year)->sum('amount'),
            'totalLastYear'  => (clone $base)->whereYear('date', now()->subYear()->year)->sum('amount'),
            'totalAll'       => (clone $base)->sum('amount'),
        ];
    }

    public function feeTotals(): array
    {
        $fees = Transaction::where('user_id', Auth::id())->where('is_transaction_fee', true);

        return [
            'totalFeesThisMonth' => (clone $fees)->whereMonth('date', now()->month)->whereYear('date', now()->year)->sum('amount'),
            'totalFeesLastMonth' => (clone $fees)->whereMonth('date', now()->subMonth()->month)->whereYear('date', now()->subMonth()->year)->sum('amount'),
            'totalFeesAll'       => (clone $fees)->sum('amount'),
        ];
    }

    public function summary(): array
    {
        $rows = $this->summaryBaseQuery()
            ->selectRaw('transactions.mobile_money_type, categories.type as category_type, SUM(transactions.amount) as total')
            ->groupBy('transactions.mobile_money_type', 'categories.type')
            ->get();

        $merged = [];
        foreach ($rows as $row) {
            $type        = $row->mobile_money_type ?? 'other';
            $dir         = $row->category_type === 'income' ? 'in' : 'out';
            $merged[$type][$dir] = ($merged[$type][$dir] ?? 0) + (float) $row->total;
        }

        $typeLabels = [
            'send_money'        => 'Send Money',
            'paybill'           => 'Lipa Na M-Pesa (PayBill)',
            'buy_goods'         => 'Lipa Na M-Pesa (Buy Goods)',
            'pochi_la_biashara' => 'Pochi La Biashara',
            'other'             => 'Others',
        ];

        $summary = [];
        foreach ($typeLabels as $key => $label) {
            $summary[$key] = [
                'label'    => $label,
                'paid_in'  => $merged[$key]['in']  ?? 0,
                'paid_out' => $merged[$key]['out'] ?? 0,
            ];
        }

        // Roll unknown types into "other"
        foreach ($merged as $key => $dirs) {
            if (!array_key_exists($key, $typeLabels)) {
                $summary['other']['paid_in']  += $dirs['in']  ?? 0;
                $summary['other']['paid_out'] += $dirs['out'] ?? 0;
            }
        }

        return array_values($summary);
    }

    public function periodStats(): array
    {
        $m  = now()->month;
        $y  = now()->year;
        $lm = now()->subMonth()->month;
        $ly = now()->subMonth()->year;
        $lastYear = now()->subYear()->year;

        $result = $this->summaryBaseQuery()
            ->selectRaw("
                SUM(CASE WHEN categories.type='income'  AND MONTH(transactions.date)=? AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as month_in,
                SUM(CASE WHEN categories.type='expense' AND MONTH(transactions.date)=? AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as month_out,
                SUM(CASE WHEN categories.type='income'  AND MONTH(transactions.date)=? AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as last_month_in,
                SUM(CASE WHEN categories.type='expense' AND MONTH(transactions.date)=? AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as last_month_out,
                SUM(CASE WHEN categories.type='income'  AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as year_in,
                SUM(CASE WHEN categories.type='expense' AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as year_out,
                SUM(CASE WHEN categories.type='income'  AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as last_year_in,
                SUM(CASE WHEN categories.type='expense' AND YEAR(transactions.date)=? THEN transactions.amount ELSE 0 END) as last_year_out,
                SUM(CASE WHEN categories.type='income'  THEN transactions.amount ELSE 0 END) as all_in,
                SUM(CASE WHEN categories.type='expense' THEN transactions.amount ELSE 0 END) as all_out
            ", [$m, $y, $m, $y, $lm, $ly, $lm, $ly, $y, $y, $lastYear, $lastYear])
            ->first();

        $periods = [
            'This Month' => ['in' => (float) ($result->month_in ?? 0),      'out' => (float) ($result->month_out ?? 0)],
            'Last Month' => ['in' => (float) ($result->last_month_in ?? 0), 'out' => (float) ($result->last_month_out ?? 0)],
            'This Year'  => ['in' => (float) ($result->year_in ?? 0),       'out' => (float) ($result->year_out ?? 0)],
            'Last Year'  => ['in' => (float) ($result->last_year_in ?? 0),  'out' => (float) ($result->last_year_out ?? 0)],
            'All Time'   => ['in' => (float) ($result->all_in ?? 0),        'out' => (float) ($result->all_out ?? 0)],
        ];

        foreach ($periods as &$data) {
            $data['net'] = (float) $data['in'] - (float) $data['out'];
        }

        return $periods;
    }

    // ── private ───────────────────────────────────────────────────────────────

    /**
     * Shared base query used by both summary() and periodStats().
     */
    private function summaryBaseQuery()
    {
        return DB::table('transactions')
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('transactions.user_id', Auth::id())
            ->whereNull('transactions.deleted_at')
            ->whereNotIn('categories.name', self::EXCLUDED_SUMMARY_CATEGORIES)
            ->whereIn('categories.type', ['income', 'expense'])
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->where('transactions.payment_method', '!=', 'Client Fund')
                        ->where('transactions.payment_method', '!=', 'Client Commission')
                        ->orWhereNull('transactions.payment_method');
                })->orWhere(function ($q2) {
                    $q2->where('transactions.payment_method', 'Client Commission')
                        ->where('categories.type', 'income');
                });
            });
    }
}
