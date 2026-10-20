<?php

namespace App\Http\Controllers;

use App\Models\RetirementEntry;
use App\Models\RetirementFund;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RetirementFundController extends Controller
{
    public const REMOVE_WINDOW_MINUTES = 5;
    public const MAX_FUNDS = 2;

    public function index()
    {
        $funds = RetirementFund::where('is_active', true)->orderBy('name')->get();
        $total = $funds->sum(fn ($f) => $f->balance());
        $canAddFund = $funds->count() < self::MAX_FUNDS;

        return view('retirement.index', compact('funds', 'total', 'canAddFund'));
    }

    public function store(Request $request)
    {
        // Enforce the limit on the server, since hiding the button alone can be bypassed.
        $fundCount = RetirementFund::where('is_active', true)->count();
        if ($fundCount >= self::MAX_FUNDS) {
            return redirect()->route('retirement.index')
                ->with('error', 'You can only have ' . self::MAX_FUNDS . ' funds.');
        }

        $data = $request->validate([
            'name'                 => 'required|string|max:100',
            'type'                 => ['required', Rule::in(array_keys(RetirementFund::SECTIONS))],
            'opening_balance'      => 'required|numeric|min:0',
            'opening_balance_date' => 'required|date',
            'notes'                => 'nullable|string|max:500',
        ]);

        $fund = RetirementFund::create($data + ['user_id' => Auth::id()]);

        return redirect()->route('retirement.show', $fund)
            ->with('success', 'Fund created. You can now record payslip figures.');
    }

    public function show(Request $request, RetirementFund $fund)
    {
        $this->authorizeFund($fund);

        // History is paged by calendar year (Jan–Dec, or from the first recorded month to Dec).
        $contributions = $fund->entries()
            ->where('entry_type', 'contribution')
            ->orderByDesc('period_date')
            ->get();

        $years        = $contributions->map(fn ($e) => $e->period_date->year)->unique()->sortDesc()->values();
        $selectedYear = (int) $request->query('year');
        if (! $years->contains($selectedYear)) {
            $selectedYear = $years->first() ?? now()->year;
        }

        // Months already recorded, per section, so the form can lock them.
        $recordedMonths = $contributions
            ->groupBy(fn ($e) => $e->period_date->format('Y-m'))
            ->map(fn ($rows) => $rows->mapWithKeys(fn ($e) => [$e->section => (float) $e->employee_amount])->all())
            ->all();

        $contributionMonths = $contributions
            ->filter(fn ($e) => $e->period_date->year === $selectedYear)
            ->groupBy(fn ($e) => $e->period_date->format('Y-m'));

        $interestEntries = $fund->entries()
            ->where('entry_type', 'interest')
            ->orderByDesc('period_date')
            ->get();

        // Interest is declared Feb–Apr for the previous year. The form is only
        // offered in that window, and only for sections not yet recorded.
        $interestYear = now()->year - 1;
        $recorded     = $interestEntries
            ->filter(fn ($e) => $e->period_date->year === $interestYear)
            ->pluck('section')
            ->all();
        $pendingSections = array_diff_key($fund->sections(), array_flip($recorded));
        $showInterestForm = $this->interestWindowOpen() && ! empty($pendingSections);

        return view('retirement.show', [
            'fund'               => $fund,
            'balance'            => $fund->balance(),
            'totals'             => $fund->totals(),
            'contributionMonths' => $contributionMonths,
            'recordedMonths'     => $recordedMonths,
            'years'              => $years,
            'selectedYear'       => $selectedYear,
            'interestEntries'    => $interestEntries,
            'interestYear'       => $interestYear,
            'pendingSections'    => $pendingSections,
            'showInterestForm'   => $showInterestForm,
        ]);
    }

    // ── Monthly payslip figures ───────────────────────────────────────────────

    public function storeContribution(Request $request, RetirementFund $fund)
    {
        $this->authorizeFund($fund);

        $request->validate([
            'month'               => 'required|date_format:Y-m',
            'sections'            => 'required|array',
            'sections.*.employee' => 'nullable|numeric|min:0',
        ]);

        $period = Carbon::createFromFormat('Y-m', $request->month)->startOfMonth();

        // Only completed months can be recorded (payslips exist after the month ends).
        if ($period->gte(now()->startOfMonth())) {
            return back()->withInput()->with(
                'error',
                'You can only record figures for months that have already ended. '
                . 'The latest month you can record is ' . now()->subMonthNoOverflow()->format('F Y') . '.'
            );
        }

        $multiplier = $fund->employerMultiplier();
        $saved      = 0;
        $locked     = 0;

        DB::transaction(function () use ($request, $fund, $period, $multiplier, &$saved, &$locked) {
            foreach (array_keys($fund->sections()) as $key) {
                $employee = (float) $request->input("sections.$key.employee", 0);

                if ($employee <= 0) {
                    continue;
                }

                // Employer is always derived from employee (NSSF: same, Provident: double).
                $employer = round($employee * $multiplier, 2);

                $keys = [
                    'retirement_fund_id' => $fund->id,
                    'entry_type'         => 'contribution',
                    'section'            => $key,
                    'period_date'        => $period->toDateString(),
                ];

                // A recorded month is locked; remove it first (within the removal window) to re-enter.
                if (RetirementEntry::where($keys)->exists()) {
                    $locked++;
                    continue;
                }

                RetirementEntry::create($keys + [
                        'user_id'          => Auth::id(),
                        'employee_amount'  => $employee,
                        'employer_amount'  => $employer,
                        'voluntary_amount' => 0,
                        'interest_amount'  => 0,
                    ]);
                $saved++;
            }
        });

        if ($saved === 0) {
            return back()->withInput()->with('error', $locked > 0
                ? $period->format('F Y') . ' is already recorded. Remove it first (possible for ' . self::REMOVE_WINDOW_MINUTES . ' minutes after saving) to re-enter it.'
                : 'Enter at least one employee amount.');
        }

        return back()->with('success', 'Saved figures for ' . $period->format('F Y') . '.');
    }

    // ── Yearly interest (added Feb–Apr for the previous year) ────────────────

    public function storeInterest(Request $request, RetirementFund $fund)
    {
        $this->authorizeFund($fund);

        if (! $this->interestWindowOpen()) {
            return back()->with('error', 'Yearly interest can only be recorded between February and April.');
        }

        $data = $request->validate([
            'section' => ['required', Rule::in(array_keys($fund->sections()))],
            'amount'  => 'required|numeric|min:0.01',
        ]);

        $year = now()->year - 1;

        RetirementEntry::updateOrCreate(
            [
                'retirement_fund_id' => $fund->id,
                'entry_type'         => 'interest',
                'section'            => $data['section'],
                'period_date'        => Carbon::create($year, 12, 31)->toDateString(),
            ],
            [
                'user_id'          => Auth::id(),
                'employee_amount'  => 0,
                'employer_amount'  => 0,
                'voluntary_amount' => 0,
                'interest_amount'  => $data['amount'],
            ]
        );

        return back()->with('success', "Interest for {$year} recorded.");
    }

    public function destroyEntry(RetirementFund $fund, RetirementEntry $entry)
    {
        $this->authorizeFund($fund);
        abort_unless($entry->retirement_fund_id === $fund->id, 404);

        // Payslip figures can only be removed for REMOVE_WINDOW_MINUTES after they were saved.
        if ($entry->entry_type === 'contribution' && $entry->updated_at->lt(now()->subMinutes(self::REMOVE_WINDOW_MINUTES))) {
            return back()->with('error', 'This entry can no longer be removed (the ' . self::REMOVE_WINDOW_MINUTES . '-minute window has passed).');
        }

        $entry->delete();

        return back()->with('success', 'Entry removed.');
    }

    private function interestWindowOpen(): bool
    {
        return in_array(now()->month, [2, 3, 4], true);
    }

    private function authorizeFund(RetirementFund $fund): void
    {
        abort_unless($fund->user_id === Auth::id(), 403);
    }
}
