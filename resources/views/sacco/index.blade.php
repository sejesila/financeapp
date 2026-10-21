@php use App\Http\Controllers\SaccoController; @endphp
@php use Carbon\Carbon; @endphp
@php use App\Models\SaccoLoan; @endphp
@php use App\Models\Sacco; @endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ $sacco->name ?? 'Sacco' }}
        </h2>
    </x-slot>

    @php
        $inp  = 'mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm';
        $card = 'bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 sm:p-6';
        $win  = SaccoController::REMOVE_WINDOW_MINUTES;
        $rate = SaccoLoan::ANNUAL_RATE;
        $maxTerm = SaccoLoan::MAX_TERM;
    @endphp

    <div class="py-3 sm:py-8">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">

            @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="bg-{{ $color }}-100 text-{{ $color }}-700 p-3 rounded text-sm">{{ session($key) }}</div>
                @endif
            @endforeach
            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-3 rounded text-sm">
                    <ul class="list-disc list-inside">@foreach($errors->all() as $e)
                            <li>{{ $e }}</li>
                        @endforeach</ul>
                </div>
            @endif

            @if(! $sacco)
                {{-- ── First-time setup ── --}}
                <div class="{{ $card }}">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">Set up your sacco</h3>
                    <p class="text-xs text-gray-500 mb-4">Enter the balances from your latest statement. Months after
                        that date are added from payslips.</p>
                    <form method="POST" action="{{ route('sacco.store') }}"
                          class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                        @csrf
                        <label class="block">Name <input name="name" required value="{{ old('name') }}"
                                                         class="{{ $inp }}"></label>
                        <label class="block">Balances as at <input type="date" name="opening_date" required
                                                                   value="{{ old('opening_date') }}" class="{{ $inp }}"></label>
                        <label class="block">Share capital <input type="number" step="0.01" min="0"
                                                                  name="opening_shares" required
                                                                  value="{{ old('opening_shares', 0) }}"
                                                                  class="{{ $inp }}"></label>
                        <label class="block">Deposits <input type="number" step="0.01" min="0" name="opening_deposits"
                                                             required value="{{ old('opening_deposits', 0) }}"
                                                             class="{{ $inp }}"></label>
                        <label class="block">Mobile wallet <input type="number" step="0.01" min="0"
                                                                  name="opening_wallet" required
                                                                  value="{{ old('opening_wallet', 0) }}"
                                                                  class="{{ $inp }}"></label>
                        <label class="block">Borrowing multiple (× deposits)
                            <input type="number" step="0.1" min="0" max="10" name="borrowing_multiple" required
                                   value="{{ old('borrowing_multiple', 3) }}" class="{{ $inp }}">
                        </label>
                        <div class="sm:col-span-2">
                            <button class="bg-teal-600 hover:bg-teal-700 text-white px-5 py-2 rounded text-sm">Save
                            </button>
                        </div>
                    </form>
                </div>
            @else
                {{-- ── Summary ── --}}
                <div class="bg-gradient-to-r from-teal-500 to-emerald-600 text-white p-4 sm:p-6 rounded-lg shadow-lg">
                    <p class="text-xs sm:text-sm font-semibold">Net position (shares + deposits + wallet − loans)</p>
                    <p class="text-3xl sm:text-4xl font-bold mt-1">KES {{ number_format($net, 0, '.', ',') }}</p>
                    <p class="text-xs mt-1 opacity-90">Balances as at {{ $sacco->opening_date->format('M d, Y') }} plus
                        figures recorded below.</p>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                    @foreach([
                        ['Shares', $balances['shares']],
                        ['Deposits', $balances['deposits']],
                        ['Wallet', $balances['wallet']],
                        ['Loans owed', $outstanding],
                        ['Can borrow', $borrowingPower],
                    ] as [$l, $v])
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3">
                            <p class="text-xs text-gray-500">{{ $l }}</p>
                            <p class="text-base font-bold text-gray-900 dark:text-white">
                                KES {{ number_format($v, 0) }}</p>
                        </div>
                    @endforeach
                </div>
                <p class="text-[11px] text-gray-400 -mt-2">"Can borrow" = deposits
                    × {{ $sacco->borrowing_multiple + 0 }} − loans owed. Check your sacco's actual rules.</p>

                {{-- ── Monthly payroll deposit ── --}}
                <div class="{{ $card }}">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">📄 Add payslip figures</h3>
                    @if($recordable)
                        @php
                            $months = [];
                            for ($m = $sacco->opening_date->copy()->startOfMonth()->addMonthNoOverflow(); $m->lte($recordable); $m->addMonthNoOverflow()) {
                                if (! in_array($m->format('Y-m'), $recordedMonths)) {
                                    $months[$m->format('Y-m')] = $m->format('F Y');
                                }
                            }
                            $nextFree = array_key_first($months);
                        @endphp
                        <p class="text-xs text-gray-500 mb-4">Latest month you can
                            record: {{ $recordable->format('F Y') }}. A recorded month is locked.</p>
                        @if($nextFree)
                            <form method="POST" action="{{ route('sacco.contributions.store') }}"
                                  class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end text-xs text-gray-600 dark:text-gray-400">
                                @csrf
                                <label class="block">Month
                                    <select name="month" required class="{{ $inp }}">
                                        @foreach($months as $key => $label)
                                            <option value="{{ $key }}" @selected($key === $nextFree)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block">Deposits <input type="number" step="0.01" min="0.01"
                                                                     name="deposits" required
                                                                     value="{{ old('deposits') }}"
                                                                     class="{{ $inp }}"></label>
                                <button class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded text-sm">Save
                                    month
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-gray-500">All available months are recorded.</p>
                        @endif
                    @else
                        <p class="text-xs text-gray-500">Nothing to record yet. Your opening balance already covers last
                            month.</p>
                    @endif
                </div>

                {{-- ── Dividend ── --}}
                @if($dividendYear)
                    <div class="{{ $card }}">
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">📈 Dividend / interest
                            for {{ $dividendYear }}</h3>
                        <p class="text-xs text-gray-500 mb-4">Record it when it is paid, and where it was credited.</p>
                        <form method="POST" action="{{ route('sacco.dividend.store') }}"
                              class="grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-xs text-gray-600 dark:text-gray-400">
                            @csrf
                            <label class="block">Amount <input type="number" step="0.01" min="0.01" name="amount"
                                                               required value="{{ old('amount') }}" class="{{ $inp }}"></label>
                            <label class="block">Credited to
                                <select name="credited_to" class="{{ $inp }}">
                                    @foreach(Sacco::LEDGERS as $k => $label)
                                        <option
                                            value="{{ $k }}" @selected(old('credited_to', 'wallet') === $k)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">Date paid <input type="date" name="date" required
                                                                  max="{{ now()->toDateString() }}"
                                                                  value="{{ old('date') }}" class="{{ $inp }}"></label>
                            <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm">
                                Save
                            </button>
                        </form>
                    </div>
                @endif

                {{-- ── Loans ── --}}
                <div class="{{ $card }}">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">💳 Loans</h3>
                    <p class="text-xs text-gray-500 mb-3">{{ $rate + 0 }}% a year on the reducing balance, up
                        to {{ $maxTerm }} months. One active loan at a time.</p>

                    @forelse($loans as $loan)
                        @php
                            $out = $loan->outstanding();
                            $paidPct = $loan->principal > 0 ? min(100, round((1 - $out / (float) $loan->principal) * 100)) : 0;
                            $payMin = $loan->disbursed_date->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m');
                            $payMax = now()->subMonthNoOverflow()->format('Y-m');
                            $recorded = $loan->payments->map(fn ($p) => $p->period_date->format('Y-m'))->all();
                            $loanMonths = [];
                            for ($m = Carbon::parse($payMin . '-01'); $m->format('Y-m') <= $payMax; $m->addMonthNoOverflow()) {
                                if (! in_array($m->format('Y-m'), $recorded)) {
                                    $loanMonths[$m->format('Y-m')] = $m->format('F Y');
                                }
                            }
                            // Any unrecorded month can be picked; the earliest is preselected.
                            $loanNextFree = array_key_first($loanMonths);
                        @endphp
                        <div
                            class="mb-4 rounded-lg border border-gray-200 dark:border-gray-700 p-3 {{ $loan->status === 'refinanced' ? 'opacity-60' : '' }}">
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $loan->name }}
                                        @if($loan->status === 'cleared')
                                            <span class="ml-1 text-xs text-emerald-600">cleared</span>
                                        @endif
                                        @if($loan->status === 'refinanced')
                                            <span class="ml-1 text-xs text-amber-600">refinanced</span>
                                        @endif
                                        @if($loan->refinanced_from_id)
                                            <span class="ml-1 text-xs text-indigo-600">refinance</span>
                                        @endif
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        KES {{ number_format($loan->principal, 0) }} at {{ $loan->annual_rate + 0 }}%
                                        · {{ $loan->term_months }} months
                                        · instalment KES {{ number_format($loan->instalment, 2) }}
                                    </p>
                                    @if($loan->fee > 0)
                                        <p class="text-xs text-gray-500">Fees KES {{ number_format($loan->fee, 2) }}
                                            added to the balance
                                            @if($loan->amount_received !== null)
                                                · you received KES {{ number_format($loan->amount_received, 0) }} in full
                                            @endif
                                        </p>
                                    @endif
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                                        KES {{ number_format($out, 2) }}</p>
                                    <p class="text-xs text-gray-500">{{ $loan->status === 'active' ? $loan->monthsLeft() . ' months left' : '' }}</p>
                                </div>
                            </div>
                            <div class="mt-2 h-1.5 rounded bg-gray-200 dark:bg-gray-700">
                                <div class="h-1.5 rounded bg-teal-600" style="width: {{ $paidPct }}%"></div>
                            </div>

                            @if($loan->status === 'active')
                                @if($loanNextFree)
                                    <form method="POST" action="{{ route('sacco.loans.payments.store', $loan) }}"
                                          class="mt-3 grid grid-cols-1 sm:grid-cols-4 gap-3 items-end text-xs text-gray-600 dark:text-gray-400">
                                        @csrf
                                        <label class="block">Month
                                            <select name="month" required class="{{ $inp }}">
                                                @foreach($loanMonths as $key => $label)
                                                    <option value="{{ $key }}" @selected($key === $loanNextFree)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        <label class="block">Amount <input type="number" step="0.01" min="0.01"
                                                                           name="amount" required
                                                                           value="{{ $loan->instalment }}"
                                                                           class="{{ $inp }}"></label>
                                        <label class="block">Interest
                                            <input type="number" step="0.01" name="interest"
                                                   value="{{ number_format($loan->monthlyInterest(), 2, '.', '') }}"
                                                   readonly
                                                   class="{{ $inp }} bg-gray-100 dark:bg-gray-600 cursor-not-allowed">
                                        </label>
                                        <button
                                            class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded text-sm">
                                            Record instalment
                                        </button>
                                    </form>
                                @else
                                    <p class="mt-3 text-xs text-gray-500">All available months are recorded for this
                                        loan.</p>
                                @endif

                                {{-- Refinance / top-up: opens 6 months after the first repayment --}}
                                @if($loan->canRefinance())
                                    <details class="mt-3">
                                        <summary
                                            class="cursor-pointer text-xs font-semibold text-gray-800 dark:text-gray-200">
                                            🔁 Refinance / top up
                                        </summary>
                                        <p class="text-xs text-gray-500 mt-2">
                                            Your outstanding KES {{ number_format($out, 2) }} rolls into a new loan
                                            together with the top-up.
                                            Fees: 10% of the outstanding principal + 2% of the top-up + KES 200, added
                                            to the new balance. You receive the top-up in full.
                                        </p>
                                        <form method="POST" action="{{ route('sacco.loans.refinance', $loan) }}"
                                              id="refi-form" data-outstanding="{{ round($out, 2) }}"
                                              class="mt-3 grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs text-gray-600 dark:text-gray-400">
                                            @csrf
                                            <label class="block">Top-up amount <input type="number" step="0.01"
                                                                                      min="0.01" name="topup"
                                                                                      id="refi-topup" required
                                                                                      class="{{ $inp }}"></label>
                                            <label class="block">New term (months, max {{ $maxTerm }}) <input
                                                    type="number" min="1" max="{{ $maxTerm }}" name="term_months"
                                                    id="refi-term" required
                                                    value="{{ min($maxTerm, max(1, $loan->monthsLeft())) }}"
                                                    class="{{ $inp }}"></label>
                                            <label class="block">Date <input type="date" name="date" required
                                                                             max="{{ now()->toDateString() }}"
                                                                             value="{{ now()->toDateString() }}"
                                                                             class="{{ $inp }}"></label>
                                            <div id="refi-preview"
                                                 class="sm:col-span-3 text-xs text-gray-600 dark:text-gray-400"></div>
                                            <div class="sm:col-span-3">
                                                <button
                                                    class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">
                                                    Refinance
                                                </button>
                                            </div>
                                        </form>
                                    </details>
                                @else
                                    @php $refiFrom = $loan->refinanceEligibleFrom(); @endphp
                                    <p class="mt-3 text-xs text-gray-500">
                                        🔁 Refinancing opens
                                        @if($refiFrom)
                                            on {{ $refiFrom->format('M d, Y') }}
                                            , {{ SaccoLoan::REFI_WAIT_MONTHS }} months after your first
                                            repayment.
                                        @else
                                            {{ SaccoLoan::REFI_WAIT_MONTHS }} months after your first
                                            repayment. Record that first instalment to start the clock.
                                        @endif
                                    </p>
                                @endif
                            @endif

                            @if($loan->payments->isNotEmpty())
                                @php
                                    $totPaid      = $loan->payments->sum('amount');
                                    $totInterest  = $loan->payments->sum('interest_portion');
                                    $totPrincipal = $loan->payments->sum('principal_portion');
                                @endphp
                                {{-- Scrolls after ~4 rows; header and totals stay pinned --}}
                                <div class="mt-3 max-h-44 overflow-y-auto rounded border border-gray-100 dark:border-gray-700">
                                    <table class="w-full text-xs">
                                        <thead class="text-gray-500 bg-gray-50 dark:bg-gray-700 sticky top-0">
                                        <tr>
                                            <th class="text-left py-1 px-2">Month</th>
                                            <th class="text-right px-2">Paid</th>
                                            <th class="text-right px-2">Interest</th>
                                            <th class="text-right px-2">Principal</th>
                                            <th></th>
                                        </tr>
                                        </thead>
                                        <tbody
                                            class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                                        @foreach($loan->payments->sortByDesc('period_date') as $p)
                                            <tr>
                                                <td class="py-1 px-2">{{ $p->period_date->format('M Y') }}</td>
                                                <td class="text-right px-2">{{ number_format($p->amount, 2) }}</td>
                                                <td class="text-right px-2">{{ number_format($p->interest_portion, 2) }}</td>
                                                <td class="text-right px-2">{{ number_format($p->principal_portion, 2) }}</td>
                                                <td class="text-right px-2">@if($p->created_at->gt(now()->subMinutes($win)))
                                                        @include('sacco._delete', ['action' => route('sacco.loan-payments.destroy', $p)])
                                                    @endif</td>
                                            </tr>
                                        @endforeach
                                        </tbody>
                                        <tfoot
                                            class="sticky bottom-0 bg-gray-50 dark:bg-gray-700 font-semibold text-gray-900 dark:text-white border-t border-gray-200 dark:border-gray-600">
                                        <tr>
                                            <td class="py-1 px-2">Total ({{ $loan->payments->count() }})</td>
                                            <td class="text-right px-2">{{ number_format($totPaid, 2) }}</td>
                                            <td class="text-right px-2">{{ number_format($totInterest, 2) }}</td>
                                            <td class="text-right px-2">{{ number_format($totPrincipal, 2) }}</td>
                                            <td></td>
                                        </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            @elseif($loan->status === 'active' && (! $loan->refinanced_from_id || $loan->created_at->gt(now()->subMinutes($win))))
                                <div
                                    class="mt-2 text-right">@include('sacco._delete', ['action' => route('sacco.loans.destroy', $loan)])</div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-500 mb-3">No loans. You currently owe the sacco nothing.</p>
                    @endforelse

                    @if($canAddLoan)
                        <details class="mt-2">
                            <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-gray-200">+ Add
                                a loan
                            </summary>
                            <p class="text-xs text-gray-500 mt-2">You receive the full amount. The one-off fee (2% of
                                the amount + KES 200) is added to what you owe. Tick the box below for a loan that was
                                already running before you started tracking, so no fee is added.</p>
                            <form method="POST" action="{{ route('sacco.loans.store') }}" id="new-loan-form"
                                  class="mt-3 grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs text-gray-600 dark:text-gray-400">
                                @csrf
                                <label class="block col-span-2 sm:col-span-1">Name <input name="name" required
                                                                                          value="{{ old('name', 'Development loan') }}"
                                                                                          class="{{ $inp }}"></label>
                                <label class="block">Amount <input type="number" step="0.01" min="1" name="principal"
                                                                   id="nl-principal" required
                                                                   value="{{ old('principal') }}"
                                                                   class="{{ $inp }}"></label>
                                <label class="block">Term (months, max {{ $maxTerm }}) <input type="number" min="1"
                                                                                              max="{{ $maxTerm }}"
                                                                                              name="term_months"
                                                                                              id="nl-term" required
                                                                                              value="{{ old('term_months', $maxTerm) }}"
                                                                                              class="{{ $inp }}"></label>
                                <label class="block">Disbursed <input type="date" name="disbursed_date" required
                                                                      max="{{ now()->toDateString() }}"
                                                                      value="{{ old('disbursed_date', now()->toDateString()) }}"
                                                                      class="{{ $inp }}"></label>
                                <label class="flex items-center gap-2 col-span-2 pt-5">
                                    <input type="checkbox" name="existing" value="1" id="nl-existing"
                                           class="rounded border-gray-300">
                                    Already running (no fee). Enter what I currently owe as the amount.
                                </label>
                                <div id="nl-preview"
                                     class="col-span-2 sm:col-span-3 text-xs text-gray-600 dark:text-gray-400"></div>
                                <div class="col-span-2 sm:col-span-3">
                                    <button class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded text-sm">
                                        Add loan
                                    </button>
                                </div>
                            </form>
                        </details>
                    @endif
                </div>

                {{-- ── Wallet withdrawal (15 March – 30 April) ── --}}
                @if($withdrawalOpen)
                    <details class="{{ $card }}">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-gray-200">📱
                            Withdraw from mobile wallet
                        </summary>
                        <form method="POST" action="{{ route('sacco.withdrawal.store') }}"
                              class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-3 items-end text-xs text-gray-600 dark:text-gray-400">
                            @csrf
                            <label class="block">Amount <input type="number" step="0.01" min="0.01" name="amount"
                                                               required class="{{ $inp }}"></label>
                            <label class="block">Total charges <input type="number" step="0.01" min="0" name="charges"
                                                                      class="{{ $inp }}"></label>
                            <label class="block">Date <input type="date" name="date" required
                                                             max="{{ now()->toDateString() }}"
                                                             value="{{ now()->toDateString() }}"
                                                             class="{{ $inp }}"></label>
                            <label class="block">Note <input name="description" maxlength="100" placeholder="To M-PESA"
                                                             class="{{ $inp }}"></label>
                            <div class="col-span-2 sm:col-span-4">
                                <button class="bg-gray-700 hover:bg-gray-800 text-white px-4 py-2 rounded text-sm">
                                    Record withdrawal
                                </button>
                            </div>
                        </form>
                    </details>
                @elseif($balances['wallet'] > 0)
                    <p class="text-xs text-gray-500">Wallet withdrawals are open from 15 March to 30 April.</p>
                @endif

                {{-- ── Recent activity ── --}}
                <div class="{{ $card }}">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">Recent activity</h3>
                    @if($entries->isEmpty())
                        <p class="text-sm text-gray-500">Nothing recorded yet.</p>
                    @else
                        <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-xs">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                                <tr>
                                    <th class="px-3 py-2 text-left">Date</th>
                                    <th class="px-3 py-2 text-left">Ledger</th>
                                    <th class="px-3 py-2 text-left">Type</th>
                                    <th class="px-3 py-2 text-right">Amount</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                                </thead>
                                <tbody
                                    class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                                @foreach($entries as $e)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap">{{ $e->period_date->format('M d, Y') }}</td>
                                        <td class="px-3 py-2">{{ Sacco::LEDGERS[$e->ledger] ?? $e->ledger }}</td>
                                        <td class="px-3 py-2">{{ Sacco::KINDS[$e->kind] ?? $e->kind }}</td>
                                        <td class="px-3 py-2 text-right font-semibold {{ $e->amount < 0 ? 'text-red-600' : 'text-emerald-600' }}">{{ ($e->amount < 0 ? '−' : '+') . number_format(abs($e->amount), 2) }}</td>
                                        <td class="px-3 py-2 text-right">@if($e->created_at->gt(now()->subMinutes($win)))
                                                @include('sacco._delete', ['action' => route('sacco.entries.destroy', $e)])
                                            @endif</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <p class="text-xs text-gray-500 mt-2">Entries can be removed for {{ $win }} minutes after
                            saving.</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    @if($sacco)
        <script>
            (function () {
                const RATE = {{ $rate }};
                const money = n => 'KES ' + n.toLocaleString('en-US', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
                const pmt = (p, n) => {
                    const r = RATE / 1200;
                    return r > 0 ? p * r / (1 - Math.pow(1 + r, -n)) : p / n;
                };

                // New loan preview
                const nlP = document.getElementById('nl-principal'), nlT = document.getElementById('nl-term'),
                    nlE = document.getElementById('nl-existing'), nlOut = document.getElementById('nl-preview');

                function nl() {
                    if (!nlP || !nlOut) return;
                    const p = parseFloat(nlP.value), n = parseInt(nlT.value, 10);
                    if (!(p > 0) || !(n > 0)) {
                        nlOut.textContent = '';
                        return;
                    }
                    const fee = nlE.checked ? 0 : p * 0.02 + 200, owed = p + fee, inst = pmt(owed, n);
                    nlOut.innerHTML = (nlE.checked ? '' : 'You receive ' + money(p) + ' in full · fee ' + money(fee) + ' added · ')
                        + 'you owe ' + money(owed) + ' · instalment ' + money(inst)
                        + ' · total interest about ' + money(inst * n - owed);
                }

                [nlP, nlT, nlE].forEach(el => el && el.addEventListener('input', nl));
                nl();

                // Refinance preview
                const rf = document.getElementById('refi-form');
                if (rf) {
                    const out = parseFloat(rf.dataset.outstanding), tu = document.getElementById('refi-topup'),
                        tm = document.getElementById('refi-term'), pv = document.getElementById('refi-preview');
                    const refi = function () {
                        const t = parseFloat(tu.value), n = parseInt(tm.value, 10);
                        if (!(t > 0) || !(n > 0)) {
                            pv.textContent = '';
                            return;
                        }
                        const fee = out * 0.10 + t * 0.02 + 200, np = out + t + fee, inst = pmt(np, n);
                        pv.innerHTML = 'You receive ' + money(t) + ' in full · fees ' + money(fee) + ' added · new balance '
                            + money(np) + ' · new instalment ' + money(inst);
                    };
                    [tu, tm].forEach(el => el.addEventListener('input', refi));
                }
            })();
        </script>
    @endif
</x-app-layout>
