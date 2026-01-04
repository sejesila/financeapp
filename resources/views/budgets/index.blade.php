<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Budget Overview
                </h2>
                <p class="text-sm text-gray-500">
                    {{ $year }} annual budget performance (Auto-generated from transactions)
                </p>
            </div>

            <select onchange="location.href='{{ url('budgets') }}/' + this.value"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                @for($y = $minYear; $y <= $maxYear; $y++)
                    <option value="{{ $y }}" @selected($y == $year)>
                        {{ $y }}
                    </option>
                @endfor
            </select>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl py-10 space-y-10">

        {{-- Flash --}}
        @if(session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Budget Table (Read-Only) --}}
        <div class="space-y-4">
            <div class="flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                        Monthly Budget Details
                    </h3>
                    <p class="text-xs text-gray-500 mt-1">
                        Budgets are automatically generated from your transactions
                    </p>
                </div>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-white dark:bg-gray-800 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left w-44 font-semibold text-gray-700 dark:text-gray-300">Category</th>
                        @for($m = 1; $m <= 12; $m++)
                            <th class="px-2 py-2 text-center font-semibold text-gray-700 dark:text-gray-300">
                                {{ \Carbon\Carbon::create()->month($m)->format('M') }}
                            </th>
                        @endfor
                        <th class="px-3 py-2 text-center bg-indigo-100 dark:bg-indigo-900 font-semibold text-gray-700 dark:text-gray-300">
                            Total
                        </th>
                    </tr>
                    </thead>

                    <tbody>
                    {{-- INCOME --}}
                    <tr class="bg-green-100 dark:bg-green-900">
                        <td colspan="14" class="p-2 font-bold text-gray-800 dark:text-gray-200">INCOME</td>
                    </tr>

                    @foreach($incomeCategories as $category)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
                                {{ $category->name }}
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $actualAmount = $actuals[$category->id][$m] ?? 0;
                                    $isCurrent = ($year == date('Y') && $m == $currentMonth);
                                @endphp
                                <td class="px-2 py-2 text-center {{ $isCurrent ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                    @if($actualAmount > 0)
                                        <span class="text-green-600 dark:text-green-400 font-medium">
                                            {{ number_format($actualAmount, 0) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endfor
                            <td class="px-3 py-2 text-center font-bold bg-indigo-100 dark:bg-indigo-900 text-gray-800 dark:text-gray-200">
                                {{ number_format($category->yearly_total, 0) }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- TOTAL INCOME --}}
                    <tr class="bg-green-200 dark:bg-green-800 font-bold text-gray-800 dark:text-gray-200">
                        <td class="px-3 py-2">TOTAL INCOME</td>
                        @for($m = 1; $m <= 12; $m++)
                            @php
                                $monthTotal = 0;
                                foreach($incomeCategories as $cat) {
                                    $monthTotal += $actuals[$cat->id][$m] ?? 0;
                                }
                            @endphp
                            <td class="px-2 py-2 text-center">
                                {{ $monthTotal > 0 ? number_format($monthTotal, 0) : '—' }}
                            </td>
                        @endfor
                        <td class="px-3 py-2 text-center bg-indigo-200 dark:bg-indigo-800">
                            {{ number_format($incomeCategories->sum('yearly_total'), 0) }}
                        </td>
                    </tr>

                    {{-- EXPENSES --}}
                    <tr class="bg-red-100 dark:bg-red-900">
                        <td colspan="14" class="p-2 font-bold text-gray-800 dark:text-gray-200">EXPENSES</td>
                    </tr>

                    @foreach($expenseCategories as $category)
                        <tr class="border-b hover:bg-gray-50 dark:hover:bg-gray-700">
                            <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">
                                {{ $category->name }}
                            </td>
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $actualAmount = $actuals[$category->id][$m] ?? 0;
                                    $isCurrent = ($year == date('Y') && $m == $currentMonth);
                                @endphp
                                <td class="px-2 py-2 text-center {{ $isCurrent ? 'bg-blue-50 dark:bg-blue-900/20' : '' }}">
                                    @if($actualAmount > 0)
                                        <span class="text-red-600 dark:text-red-400 font-medium">
                                            {{ number_format($actualAmount, 0) }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            @endfor
                            <td class="px-3 py-2 text-center font-bold bg-indigo-100 dark:bg-indigo-900 text-gray-800 dark:text-gray-200">
                                {{ number_format($category->yearly_total, 0) }}
                            </td>
                        </tr>
                    @endforeach

                    {{-- TOTAL EXPENSES --}}
                    <tr class="bg-red-200 dark:bg-red-800 font-bold text-gray-800 dark:text-gray-200">
                        <td class="px-3 py-2">TOTAL EXPENSES</td>
                        @for($m = 1; $m <= 12; $m++)
                            @php
                                $monthTotal = 0;
                                foreach($expenseCategories as $cat) {
                                    $monthTotal += $actuals[$cat->id][$m] ?? 0;
                                }
                            @endphp
                            <td class="px-2 py-2 text-center">
                                {{ $monthTotal > 0 ? number_format($monthTotal, 0) : '—' }}
                            </td>
                        @endfor
                        <td class="px-3 py-2 text-center bg-indigo-200 dark:bg-indigo-800">
                            {{ number_format($expenseCategories->sum('yearly_total'), 0) }}
                        </td>
                    </tr>

                    {{-- NET INCOME --}}
                    <tr class="bg-blue-200 dark:bg-blue-800 font-bold text-gray-800 dark:text-gray-200">
                        <td class="px-3 py-2">NET INCOME</td>
                        @for($m = 1; $m <= 12; $m++)
                            @php
                                $monthIncome = 0;
                                foreach($incomeCategories as $cat) {
                                    $monthIncome += $actuals[$cat->id][$m] ?? 0;
                                }

                                $monthExpense = 0;
                                foreach($expenseCategories as $cat) {
                                    $monthExpense += $actuals[$cat->id][$m] ?? 0;
                                }

                                $netAmount = $monthIncome - $monthExpense;
                            @endphp
                            <td class="px-2 py-2 text-center">
                                @if($netAmount != 0)
                                    <span class="{{ $netAmount < 0 ? 'text-red-600' : 'text-green-600' }}">
                                        {{ number_format($netAmount, 0) }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                        @endfor
                        <td class="px-3 py-2 text-center bg-indigo-200 dark:bg-indigo-800">
                            @php
                                $yearlyNet = $incomeCategories->sum('yearly_total') - $expenseCategories->sum('yearly_total');
                            @endphp
                            <span class="{{ $yearlyNet < 0 ? 'text-red-600' : 'text-green-600' }}">
                                {{ number_format($yearlyNet, 0) }}
                            </span>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Liabilities --}}
        <section class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                    Liabilities & Loans
                </h3>
                <a href="{{ route('loans.index') }}"
                   class="text-sm text-blue-600 hover:text-blue-800">
                    View all →
                </a>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="rounded-lg border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20 p-4">
                    <p class="text-xs text-gray-500 mb-1">Loans Taken ({{ $year }})</p>
                    <p class="text-xl font-bold text-purple-600">
                        {{ number_format($loanStats['disbursed']) }}
                    </p>
                </div>

                <div class="rounded-lg border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20 p-4">
                    <p class="text-xs text-gray-500 mb-1">Loan Payments ({{ $year }})</p>
                    <p class="text-xl font-bold text-orange-600">
                        {{ number_format($loanStats['payments']) }}
                    </p>
                </div>

                <div class="rounded-lg border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-4">
                    <p class="text-xs text-gray-500 mb-1">Active Loan Balance</p>
                    <p class="text-xl font-bold text-red-600">
                        {{ number_format($loanStats['active_balance']) }}
                    </p>
                </div>
            </div>

            <div class="rounded-md border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 p-3 text-xs text-yellow-800 dark:text-yellow-200">
                <strong>Note:</strong> Loans are not income. Repayments and interest are included as expenses.
            </div>
        </section>

    </div>
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
