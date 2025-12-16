<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Budget Overview') }} — {{ $year }}
            </h2>

            <form method="GET" action="{{ url('budgets') }}" class="flex items-center space-x-2">
                <select name="year" onchange="window.location.href='{{ url('budgets') }}/' + this.value"
                        class="border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 rounded-md shadow-sm p-2 text-base">
                    @for($y = $minYear; $y <= $maxYear; $y++)
                        <option value="{{ $y }}" @if($y == $year) selected @endif>{{ $y }}</option>
                    @endfor
                </select>
            </form>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Budget Progress Summary -->
            <div class="mb-6">
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200 mb-4">Budget Usage Summary</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    @foreach($expenseCategories as $category)
                        @php
                            $actualYearly = $category->yearly_total;
                            $budgetYearly = $category->yearly_budget;
                            $percentage = $category->budget_percentage;

                            // Determine color based on percentage
                            if ($percentage >= 100) {
                                $colorClass = 'border-red-500 bg-red-50 dark:bg-red-900/20';
                                $barColor = 'bg-red-500';
                                $textColor = 'text-red-700 dark:text-red-400';
                            } elseif ($percentage >= 80) {
                                $colorClass = 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20';
                                $barColor = 'bg-yellow-500';
                                $textColor = 'text-yellow-700 dark:text-yellow-400';
                            } elseif ($percentage >= 60) {
                                $colorClass = 'border-blue-500 bg-blue-50 dark:bg-blue-900/20';
                                $barColor = 'bg-blue-500';
                                $textColor = 'text-blue-700 dark:text-blue-400';
                            } else {
                                $colorClass = 'border-green-500 bg-green-50 dark:bg-green-900/20';
                                $barColor = 'bg-green-500';
                                $textColor = 'text-green-700 dark:text-green-400';
                            }
                        @endphp

                        <div class="border-l-4 {{ $colorClass }} p-4 rounded">
                            <h3 class="font-semibold text-gray-800 dark:text-gray-200 mb-2">{{ $category->name }}</h3>
                            <div class="mb-2">
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="text-gray-600 dark:text-gray-400">{{ number_format($actualYearly, 0, '.', ',') }}</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ number_format($budgetYearly, 0, '.', ',') }}</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="{{ $barColor }} h-2 rounded-full" style="width: {{ min($percentage, 100) }}%"></div>
                                </div>
                            </div>
                            <p class="text-sm font-bold {{ $textColor }}">
                                {{ $percentage }}% used
                                @if($percentage >= 100)
                                    <span class="text-xs">(Over budget!)</span>
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Budget Table -->
            <form method="POST" action="{{ route('budgets.update') }}">
                @csrf
                <input type="hidden" name="year" value="{{ $year }}">

                <div class="mb-4 flex justify-end">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-6 py-2 rounded">
                        Save Budgets
                    </button>
                </div>

                <div class="overflow-auto border border-gray-300 dark:border-gray-700 rounded shadow bg-white dark:bg-gray-800">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-200 dark:bg-gray-700 text-gray-800 dark:text-gray-200">
                        <tr class="border-b border-gray-300 dark:border-gray-600">
                            <th class="p-2 border border-gray-300 dark:border-gray-600 w-48 text-left">Category</th>

                            @for($m = 1; $m <= 12; $m++)
                                <th class="p-2 border border-gray-300 dark:border-gray-600 text-center w-28">
                                    <div class="font-semibold">
                                        {{ \Carbon\Carbon::create()->month($m)->format('M') }}
                                    </div>
                                </th>
                            @endfor

                            <th class="p-2 border border-gray-300 dark:border-gray-600 text-center w-28 font-semibold bg-indigo-100 dark:bg-indigo-900">Total</th>
                        </tr>
                        </thead>

                        <tbody>
                        {{-- INCOME SECTION --}}
                        <tr class="bg-green-100 dark:bg-green-900">
                            <td colspan="14" class="p-2 border border-gray-300 dark:border-gray-600 font-bold text-green-800 dark:text-green-200 text-base">
                                INCOME
                            </td>
                        </tr>

                        @php
                            $monthlyIncomeTotal = array_fill(1, 12, 0);
                        @endphp

                        @foreach($incomeCategories as $category)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 odd:bg-white dark:odd:bg-gray-800 even:bg-green-50 dark:even:bg-green-900/20">
                                <td class="p-2 border border-gray-300 dark:border-gray-600 font-medium text-gray-700 dark:text-gray-300">
                                    {{ $category->name }}
                                </td>

                                @php
                                    $rowTotal = 0;
                                @endphp

                                @for($m = 1; $m <= 12; $m++)
                                    @php
                                        $key = $category->id . '-' . $m;
                                        $budget = $budgets->get($key)->amount ?? 0;
                                        $actual = $actuals[$category->id][$m] ?? 0;
                                        $rowTotal += $actual;
                                        $monthlyIncomeTotal[$m] += $actual;
                                    @endphp

                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number"
                                               name="budgets[{{ $key }}][amount]"
                                               value="{{ $budget }}"
                                               class="w-full text-right border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 rounded p-1"
                                               step="0.01">
                                        <input type="hidden" name="budgets[{{ $key }}][category_id]" value="{{ $category->id }}">
                                        <input type="hidden" name="budgets[{{ $key }}][month]" value="{{ $m }}">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 text-right">
                                            {{ $actual > 0 ? number_format($actual, 0, '.', ',') : '-' }}
                                        </div>
                                    </td>
                                @endfor

                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right font-semibold bg-green-50 dark:bg-green-900/30">
                                    {{ number_format($rowTotal, 0, '.', ',') }}
                                </td>
                            </tr>
                        @endforeach

                        {{-- TOTAL INCOME ROW --}}
                        <tr class="bg-green-200 dark:bg-green-800 font-bold">
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-green-900 dark:text-green-100">Total Income</td>
                            @php $totalIncome = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right text-green-900 dark:text-green-100">
                                    {{ $monthlyIncomeTotal[$m] > 0 ? number_format($monthlyIncomeTotal[$m], 0, '.', ',') : '-' }}
                                </td>
                                @php $totalIncome += $monthlyIncomeTotal[$m]; @endphp
                            @endfor
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-right text-green-900 dark:text-green-100 bg-green-50 dark:bg-green-900/30">
                                {{ number_format($totalIncome, 0, '.', ',') }}
                            </td>
                        </tr>

                        {{-- EXPENSES SECTION --}}
                        <tr class="bg-red-100 dark:bg-red-900">
                            <td colspan="14" class="p-2 border border-gray-300 dark:border-gray-600 font-bold text-red-800 dark:text-red-200 text-base">
                                EXPENSES
                            </td>
                        </tr>

                        @php
                            $monthlyExpenseTotal = array_fill(1, 12, 0);
                        @endphp

                        @foreach($expenseCategories as $category)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 odd:bg-white dark:odd:bg-gray-800 even:bg-gray-50 dark:even:bg-gray-700">
                                <td class="p-2 border border-gray-300 dark:border-gray-600 font-medium text-gray-700 dark:text-gray-300">
                                    {{ $category->name }}
                                    @if($year == date('Y'))
                                        @php
                                            $key = $category->id . '-' . $currentMonth;
                                            $monthBudget = $budgets->get($key)->amount ?? 0;
                                            $monthActual = $actuals[$category->id][$currentMonth] ?? 0;
                                            $monthPercentage = $monthBudget > 0 ? round(($monthActual / $monthBudget) * 100, 0) : 0;
                                        @endphp
                                        @if($monthBudget > 0)
                                            <div class="text-xs mt-1">
                                                <span class="
                                                    {{ $monthPercentage >= 100 ? 'text-red-600 dark:text-red-400' : ($monthPercentage >= 80 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400') }}
                                                    font-semibold
                                                ">
                                                    {{ $monthPercentage }}% of {{ date('M') }} budget
                                                </span>
                                            </div>
                                        @endif
                                    @endif
                                </td>

                                @php
                                    $rowTotal = 0;
                                @endphp

                                @for($m = 1; $m <= 12; $m++)
                                    @php
                                        $key = $category->id . '-' . $m;
                                        $budget = $budgets->get($key)->amount ?? 0;
                                        $actual = $actuals[$category->id][$m] ?? 0;
                                        $rowTotal += $actual;
                                        $monthlyExpenseTotal[$m] += $actual;
                                    @endphp

                                    <td class="p-2 border border-gray-300 dark:border-gray-600">
                                        <input type="number"
                                               name="budgets[{{ $key }}][amount]"
                                               value="{{ $budget }}"
                                               class="w-full text-right border border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 rounded p-1"
                                               step="0.01">
                                        <input type="hidden" name="budgets[{{ $key }}][category_id]" value="{{ $category->id }}">
                                        <input type="hidden" name="budgets[{{ $key }}][month]" value="{{ $m }}">
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 text-right">
                                            {{ $actual > 0 ? number_format($actual, 0, '.', ',') : '-' }}
                                        </div>
                                    </td>
                                @endfor

                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right font-semibold bg-indigo-50 dark:bg-indigo-900/30">
                                    {{ number_format($rowTotal, 0, '.', ',') }}
                                </td>
                            </tr>
                        @endforeach

                        {{-- TOTAL EXPENSES ROW --}}
                        <tr class="bg-red-200 dark:bg-red-800 font-bold">
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-red-900 dark:text-red-100">Total Expenses</td>
                            @php $totalExpenses = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right text-red-900 dark:text-red-100">
                                    {{ $monthlyExpenseTotal[$m] > 0 ? number_format($monthlyExpenseTotal[$m], 0, '.', ',') : '-' }}
                                </td>
                                @php $totalExpenses += $monthlyExpenseTotal[$m]; @endphp
                            @endfor
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-right text-red-900 dark:text-red-100 bg-red-50 dark:bg-red-900/30">
                                {{ number_format($totalExpenses, 0, '.', ',') }}
                            </td>
                        </tr>

                        {{-- NET REMAINING ROW --}}
                        <tr class="bg-blue-200 dark:bg-blue-800 font-bold text-base">
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-blue-900 dark:text-blue-100">Net Remaining</td>
                            @php $totalNet = 0; @endphp
                            @for($m = 1; $m <= 12; $m++)
                                @php
                                    $net = $monthlyIncomeTotal[$m] - $monthlyExpenseTotal[$m];
                                    $totalNet += $net;
                                @endphp
                                <td class="p-2 border border-gray-300 dark:border-gray-600 text-right {{ $net >= 0 ? 'text-blue-900 dark:text-blue-100' : 'text-red-700 dark:text-red-400' }}">
                                    {{ number_format($net, 0, '.', ',') }}
                                </td>
                            @endfor
                            <td class="p-2 border border-gray-300 dark:border-gray-600 text-right {{ $totalNet >= 0 ? 'text-blue-900 dark:text-blue-100 bg-blue-50 dark:bg-blue-900/30' : 'text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/30' }}">
                                {{ number_format($totalNet, 0, '.', ',') }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </form>

            <!-- Liabilities Section -->
            <div class="mt-8 bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-xl font-bold text-gray-800 dark:text-gray-200">Liabilities & Loans</h2>
                    <a href="{{ route('loans.index') }}" class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                        View All Loans →
                    </a>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20 p-4 rounded">
                        <h3 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Loans Taken ({{ $year }})</h3>
                        <p class="text-xl font-bold text-purple-700 dark:text-purple-400">
                            KES {{ number_format($loanStats['disbursed'], 0, '.', ',') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">New loans this year</p>
                    </div>

                    <div class="border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20 p-4 rounded">
                        <h3 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Loan Payments ({{ $year }})</h3>
                        <p class="text-xl font-bold text-orange-700 dark:text-orange-400">
                            KES {{ number_format($loanStats['payments'], 0, '.', ',') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Principal + Interest paid</p>
                    </div>

                    <div class="border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-4 rounded">
                        <h3 class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Active Loan Balance</h3>
                        <p class="text-xl font-bold text-red-700 dark:text-red-400">
                            KES {{ number_format($loanStats['active_balance'], 0, '.', ',') }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Total outstanding</p>
                    </div>
                </div>

                <div class="mt-4 p-3 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 rounded">
                    <p class="text-xs text-yellow-800 dark:text-yellow-200">
                        <strong>Note:</strong> Loans are not counted as income. Only actual income (salary, side hustles, etc.) appears in the budget above. Loan repayments and interest are included in expenses.
                    </p>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
