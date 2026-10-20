<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">{{ $fund->name }}</h2>
            <a href="{{ route('retirement.index') }}" class="text-indigo-600 dark:text-indigo-400 text-sm">← Back</a>
        </div>
    </x-slot>

    @php
        $inputClass    = 'mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm';
        $readonlyClass = $inputClass . ' bg-gray-100 dark:bg-gray-600 cursor-not-allowed';
        $sections      = $fund->sections();

        // Recordable range: from the month after the opening balance, up to the last completed month.
        $lastMonth = now()->subMonthNoOverflow()->format('Y-m');
        $minMonth  = $fund->opening_balance_date
            ? $fund->opening_balance_date->copy()->startOfMonth()->addMonthNoOverflow()->format('Y-m')
            : null;
        $defaultMonth = ($minMonth && $minMonth > $lastMonth) ? $minMonth : $lastMonth;
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
                    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Balance --}}
            <div class="bg-gradient-to-r from-slate-600 to-indigo-700 text-white p-4 sm:p-6 rounded-lg shadow-lg">
                <p class="text-xs sm:text-sm font-semibold">Current balance</p>
                <p class="text-3xl sm:text-4xl font-bold mt-1">KES {{ number_format($balance, 0, '.', ',') }}</p>
                <p class="text-xs mt-1 opacity-90">
                    Opening KES {{ number_format($fund->opening_balance, 0) }}
                    @if($fund->opening_balance_date) as at {{ $fund->opening_balance_date->format('M d, Y') }} @endif
                    + figures recorded below
                </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                @foreach([['Employee', $totals->employee], ['Employer', $totals->employer], ['Interest', $totals->interest]] as [$label, $val])
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-3">
                        <p class="text-xs text-gray-500">{{ $label }} (since tracking)</p>
                        <p class="text-lg font-bold text-gray-900 dark:text-white">KES {{ number_format($val, 0) }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Payslip form --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 sm:p-6">
                <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">📄 Add monthly figures</h3>
{{--                <p class="text-xs text-gray-500 mb-4">--}}
{{--                    Enter the employee amount per section. The employer amount is filled in automatically for each section.--}}
{{--                    A month that is already recorded is locked. --}}
{{--                    You can record months that have ended--}}
{{--                    @if($minMonth)--}}
{{--                        and come after your opening balance ({{ \Carbon\Carbon::createFromFormat('Y-m', $minMonth)->format('M Y') }} onward).--}}
{{--                    @else--}}
{{--                        .--}}
{{--                    @endif--}}
{{--                </p>--}}
                <form method="POST" action="{{ route('retirement.contributions.store', $fund) }}" class="space-y-4"
                      id="payslip-form"
                      data-recorded="{{ json_encode($recordedMonths) }}">
                    @csrf
                    <label class="block text-sm w-full sm:w-56">Month
                        <input type="month" name="month" required
                               @if($minMonth) min="{{ $minMonth }}" @endif
                               max="{{ $lastMonth }}"
                               value="{{ old('month', $defaultMonth) }}"
                               class="{{ $inputClass }}">
                    </label>
                    <p id="future-note" class="hidden text-xs text-amber-600 dark:text-amber-400">
                        Figures can only be added for months that have ended.
                    </p>
                    <p id="before-note" class="hidden text-xs text-amber-600 dark:text-amber-400">
                        This month is already included in your opening balance
                        @if($fund->opening_balance_date) (as at {{ $fund->opening_balance_date->format('M d, Y') }}) @endif.
                    </p>

                    @foreach($sections as $key => $label)
                        @php $mult = $fund->employerMultiplier($key); @endphp
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 p-3">
                            <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-2">{{ $label }}
                                <span data-locked-note="{{ $key }}" class="hidden text-xs font-normal text-amber-600 dark:text-amber-400">· already recorded for this month</span>
                            </p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs text-gray-600 dark:text-gray-400">
                                <label class="block">Employee
                                    <input type="number" step="0.01" min="0" inputmode="decimal"
                                           name="sections[{{ $key }}][employee]"
                                           data-employee="{{ $key }}"
                                           data-multiplier="{{ $mult }}"
                                           value="{{ old("sections.$key.employee") }}" class="{{ $inputClass }}">
                                </label>
                                <label class="block">Employer {{ $mult === 1 ? '(= employee)' : '(= employee × ' . $mult . ')' }}
                                    <input type="number" step="0.01" readonly tabindex="-1"
                                           data-employer="{{ $key }}"
                                           class="{{ $readonlyClass }}">
                                </label>
                            </div>
                        </div>
                    @endforeach

                    <button id="payslip-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-5 py-2 rounded text-sm disabled:opacity-50 disabled:cursor-not-allowed">Save month</button>
                </form>
            </div>

            {{-- Interest: form only appears Feb–Apr, and only until the previous year's interest is recorded --}}
            @if($showInterestForm || $interestEntries->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 sm:p-6">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-1">📈 Yearly interest</h3>

                    @if($showInterestForm)
                        <p class="text-xs text-gray-500 mb-4">Record the interest declared for {{ $interestYear }}. It is added to your balance when you save it.</p>
                        <form method="POST" action="{{ route('retirement.interest.store', $fund) }}" class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end text-xs text-gray-600 dark:text-gray-400">
                            @csrf
                            <label class="block">Section ({{ $interestYear }})
                                <select name="section" class="{{ $inputClass }}">
                                    @foreach($pendingSections as $key => $label)
                                        <option value="{{ $key }}" @selected(old('section') === $key)>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">Interest amount
                                <input type="number" step="0.01" min="0.01" name="amount" required value="{{ old('amount') }}" class="{{ $inputClass }}">
                            </label>
                            <button class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded text-sm">Save interest</button>
                        </form>
                    @endif

                    @if($interestEntries->isNotEmpty())
                        <div class="{{ $showInterestForm ? 'mt-4' : 'mt-2' }} divide-y divide-gray-100 dark:divide-gray-700 text-sm">
                            @foreach($interestEntries as $e)
                                <div class="flex items-center justify-between py-2">
                                    <span class="text-gray-700 dark:text-gray-300">{{ $e->period_date->year }} · {{ $sections[$e->section] ?? $e->section }}</span>
                                    <span class="flex items-center gap-3">
                                        <span class="font-semibold text-emerald-600 dark:text-emerald-400">+{{ number_format($e->interest_amount, 2) }}</span>
                                        @include('retirement._delete', ['fund' => $fund, 'entry' => $e])
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            {{-- History --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 sm:p-6">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">Monthly history</h3>
                    @if($years->count() > 1)
                        <div class="flex flex-wrap gap-1 text-xs">
                            @foreach($years as $y)
                                <a href="{{ route('retirement.show', [$fund, 'year' => $y]) }}"
                                   class="px-2.5 py-1 rounded border {{ $y === $selectedYear
                                        ? 'bg-indigo-600 border-indigo-600 text-white'
                                        : 'border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700' }}">{{ $y }}</a>
                            @endforeach
                        </div>
                    @endif
                </div>

                @if($contributionMonths->isEmpty())
                    <p class="text-sm text-gray-500">No months recorded yet.</p>
                @else
                    @php $yearEntries = $contributionMonths->flatten(1); @endphp
                    <div class="overflow-x-auto rounded border border-gray-200 dark:border-gray-700">
                        <table class="w-full text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-3 py-2 text-left">Month</th>
                                @if(count($sections) > 1)<th class="px-3 py-2 text-left">Section</th>@endif
                                <th class="px-3 py-2 text-right">Employee</th>
                                <th class="px-3 py-2 text-right">Employer</th>
                                <th class="px-3 py-2 text-right">Total</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700 text-gray-800 dark:text-gray-200">
                            @foreach($contributionMonths as $ym => $rows)
                                @foreach($rows as $e)
                                    <tr>
                                        <td class="px-3 py-2 whitespace-nowrap">
                                            @if($loop->first){{ \Carbon\Carbon::createFromFormat('Y-m', $ym)->format('M Y') }}@endif
                                        </td>
                                        @if(count($sections) > 1)
                                            <td class="px-3 py-2">{{ $sections[$e->section] ?? $e->section }}</td>
                                        @endif
                                        <td class="px-3 py-2 text-right">{{ number_format($e->employee_amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($e->employer_amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right">{{ number_format($e->employee_amount + $e->employer_amount, 2) }}</td>
                                        <td class="px-3 py-2 text-right whitespace-nowrap">
                                            @if($e->updated_at->gt(now()->subMinutes(\App\Http\Controllers\RetirementFundController::REMOVE_WINDOW_MINUTES)))
                                                @include('retirement._delete', ['fund' => $fund, 'entry' => $e])
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                            </tbody>
                            <tfoot class="bg-gray-50 dark:bg-gray-700 font-semibold text-gray-800 dark:text-gray-200">
                            <tr>
                                <td class="px-3 py-2" @if(count($sections) > 1) colspan="2" @endif>{{ $selectedYear }} total</td>
                                <td class="px-3 py-2 text-right">{{ number_format($yearEntries->sum('employee_amount'), 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($yearEntries->sum('employer_amount'), 2) }}</td>
                                <td class="px-3 py-2 text-right">{{ number_format($yearEntries->sum('employee_amount') + $yearEntries->sum('employer_amount'), 2) }}</td>
                                <td></td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
{{--                    <p class="text-xs text-gray-500 mt-2">Entries can be removed for {{ \App\Http\Controllers\RetirementFundController::REMOVE_WINDOW_MINUTES }} minutes after saving.</p>--}}
                @endif
            </div>
        </div>
    </div>

    <script>
        (function () {
            const form = document.getElementById('payslip-form');
            if (!form) return;
            const recorded   = JSON.parse(form.dataset.recorded || '{}');
            const month      = form.querySelector('[name="month"]');
            const saveBtn    = document.getElementById('payslip-save');
            const futureNote = document.getElementById('future-note');
            const beforeNote = document.getElementById('before-note');
            const lockClasses = ['bg-gray-100', 'dark:bg-gray-600', 'cursor-not-allowed'];
            const fields = [];

            form.querySelectorAll('[data-employee]').forEach(function (employee) {
                const key        = employee.dataset.employee;
                const multiplier = parseFloat(employee.dataset.multiplier) || 1;
                const employer   = form.querySelector('[data-employer="' + key + '"]');
                const note       = form.querySelector('[data-locked-note="' + key + '"]');

                const sync = function () {
                    const v = parseFloat(employee.value);
                    employer.value = isNaN(v) ? '' : (v * multiplier).toFixed(2);
                };
                employee.addEventListener('input', sync);
                fields.push({ key: key, employee: employee, note: note, sync: sync });
            });

            // A month that already has figures is shown filled in and locked.
            // Months that have not ended, or that the opening balance already covers, are blocked too.
            const applyMonth = function () {
                const rec      = recorded[month.value] || {};
                // 'YYYY-MM' strings compare correctly as plain strings
                const isFuture = month.value !== '' && month.max && month.value > month.max;
                const isBefore = month.value !== '' && month.min && month.value < month.min;
                const outOfRange = isFuture || isBefore;
                let lockedCount = 0;

                fields.forEach(function (f) {
                    const isLocked = !outOfRange && Object.prototype.hasOwnProperty.call(rec, f.key);
                    const blocked  = isLocked || outOfRange;

                    if (isLocked) {
                        f.employee.value = Number(rec[f.key]).toFixed(2);
                        f.employee.dataset.locked = '1';
                        lockedCount++;
                    } else if (f.employee.dataset.locked || outOfRange) {
                        f.employee.value = '';
                        delete f.employee.dataset.locked;
                    }

                    f.employee.readOnly = blocked;
                    lockClasses.forEach(function (c) { f.employee.classList.toggle(c, blocked); });
                    if (f.note) f.note.classList.toggle('hidden', !isLocked);
                    f.sync();
                });

                if (futureNote) futureNote.classList.toggle('hidden', !isFuture);
                if (beforeNote) beforeNote.classList.toggle('hidden', !isBefore);
                if (saveBtn) saveBtn.disabled = outOfRange || lockedCount === fields.length;
            };

            month.addEventListener('input', applyMonth);
            month.addEventListener('change', applyMonth);
            fields.forEach(function (f) { f.sync(); });
            applyMonth();
        })();
    </script>
</x-app-layout>
