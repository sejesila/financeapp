<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Budget Overview
                </h2>
                <p class="text-xs sm:text-sm text-gray-500">
                    {{ $year }} annual budget performance
                </p>
            </div>

            {{-- Year Selector --}}
            <div class="relative" x-data="{ open: false, selectedYear: {{ $year }} }">
                <button
                    @click="open = !open"
                    @click.outside="open = false"
                    class="flex items-center gap-2 rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 px-3 sm:px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800 transition"
                >
                    <span class="font-medium">{{ $year }}</span>
                    <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div
                    x-show="open"
                    x-transition
                    class="absolute right-0 mt-2 w-48 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-lg z-50 overflow-hidden"
                    style="display: none;"
                >
                    {{-- Recent Years --}}
                    <div class="border-b border-gray-200 dark:border-gray-700">
                        @php
                            $currentYear = date('Y');
                            $recentYears = range($currentYear, max($minYear, $currentYear - 3));
                        @endphp
                        @foreach($recentYears as $y)
                            <a
                                href="{{ url('budgets/' . $y) }}"
                                class="block px-4 py-2 text-sm hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ $y == $year ? 'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-400 font-semibold' : 'text-gray-700 dark:text-gray-300' }}"
                            >
                                {{ $y }}
                                @if($y == $currentYear)
                                    <span class="text-xs text-gray-500 dark:text-gray-400">(Current)</span>
                                @endif
                            </a>
                        @endforeach
                    </div>

                    {{-- All Years Option --}}
                    @if($maxYear - $minYear > 3)
                        <button
                            @click="$dispatch('open-all-years-modal')"
                            class="w-full px-4 py-2 text-left text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition flex items-center justify-between"
                        >
                            <span>All Years</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </button>
                    @endif
                </div>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl py-4 sm:py-10 space-y-6 sm:space-y-10 px-4 sm:px-6 lg:px-8">

        {{-- Flash --}}
        @if(session('success'))
            <div class="rounded-md bg-green-100 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Mobile: Card-based view --}}
        <div class="lg:hidden space-y-6" x-data="{ selectedMonth: {{ $currentMonth }} }">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-2">
                    Monthly Budget Details
                </h3>
                <p class="text-xs text-gray-500 mb-4">
                    Auto-generated from transactions
                </p>
            </div>

            {{-- Month Selector for Mobile --}}
            <div class="flex overflow-x-auto gap-2 pb-2 -mx-4 px-4 scrollbar-hide">
                @for($m = 1; $m <= 12; $m++)
                    <button
                        @click="selectedMonth = {{ $m }}"
                        :class="selectedMonth === {{ $m }} ? 'bg-indigo-600 text-white' : 'bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300'"
                        class="flex-shrink-0 px-4 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-sm font-medium transition"
                    >
                        {{ \Carbon\Carbon::create()->month($m)->format('M') }}
                    </button>
                @endfor
            </div>

            {{-- Monthly Breakdown Cards --}}
            <div>
                @for($m = 1; $m <= 12; $m++)
                    <div x-show="selectedMonth === {{ $m }}" class="space-y-4">
                        {{-- Income Section --}}
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-green-600 dark:text-green-400 flex items-center gap-2">
                                    <span class="text-lg">💰</span>
                                    INCOME
                                </h4>
                                @php
                                    $monthIncome = 0;
                                    foreach($incomeCategories as $cat) {
                                        $monthIncome += $actuals[$cat->id][$m] ?? 0;
                                    }
                                @endphp
                                <span class="text-lg font-bold text-green-600 dark:text-green-400">
                                    {{ number_format($monthIncome, 0) }}
                                </span>
                            </div>
                            <div class="space-y-2">
                                @foreach($incomeCategories as $category)
                                    @php
                                        $actualAmount = $actuals[$category->id][$m] ?? 0;
                                    @endphp
                                    @if($actualAmount > 0)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
                                            <span class="font-medium text-green-600 dark:text-green-400">
                                                {{ number_format($actualAmount, 0) }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Expenses Section --}}
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4">
                            <div class="flex items-center justify-between mb-3">
                                <h4 class="font-semibold text-red-600 dark:text-red-400 flex items-center gap-2">
                                    <span class="text-lg">💸</span>
                                    EXPENSES
                                </h4>
                                @php
                                    $monthExpense = 0;
                                    foreach($expenseCategories as $cat) {
                                        $monthExpense += $actuals[$cat->id][$m] ?? 0;
                                    }
                                @endphp
                                <span class="text-lg font-bold text-red-600 dark:text-red-400">
                                    {{ number_format($monthExpense, 0) }}
                                </span>
                            </div>
                            <div class="space-y-2">
                                @foreach($expenseCategories as $category)
                                    @php
                                        $actualAmount = $actuals[$category->id][$m] ?? 0;
                                    @endphp
                                    @if($actualAmount > 0)
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-700 dark:text-gray-300">{{ $category->name }}</span>
                                            <span class="font-medium text-red-600 dark:text-red-400">
                                                {{ number_format($actualAmount, 0) }}
                                            </span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        {{-- Net Income Card --}}
                        <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg shadow-sm p-4 border-2 border-blue-200 dark:border-blue-800">
                            <div class="flex items-center justify-between">
                                <h4 class="font-semibold text-blue-800 dark:text-blue-200">
                                    NET INCOME
                                </h4>
                                @php
                                    $netAmount = $monthIncome - $monthExpense;
                                @endphp
                                <span class="text-xl font-bold {{ $netAmount < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ number_format($netAmount, 0) }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endfor
            </div>

            {{-- Yearly Summary for Mobile --}}
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 space-y-3">
                <h4 class="font-semibold text-gray-800 dark:text-gray-200 mb-3">{{ $year }} Summary</h4>

                <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Income</span>
                    <span class="font-bold text-green-600 dark:text-green-400">
                        {{ number_format($incomeCategories->sum('yearly_total'), 0) }}
                    </span>
                </div>

                <div class="flex items-center justify-between pb-2 border-b border-gray-200 dark:border-gray-700">
                    <span class="text-sm text-gray-600 dark:text-gray-400">Total Expenses</span>
                    <span class="font-bold text-red-600 dark:text-red-400">
                        {{ number_format($expenseCategories->sum('yearly_total'), 0) }}
                    </span>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <span class="font-semibold text-gray-800 dark:text-gray-200">Net Income</span>
                    @php
                        $yearlyNet = $incomeCategories->sum('yearly_total') - $expenseCategories->sum('yearly_total');
                    @endphp
                    <span class="text-lg font-bold {{ $yearlyNet < 0 ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                        {{ number_format($yearlyNet, 0) }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Desktop: Table view --}}
        <div class="hidden lg:block space-y-4">
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
        <section class="rounded-lg bg-white dark:bg-gray-800 p-4 sm:p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-200">
                    Liabilities & Loans
                </h3>
                <a href="{{ route('loans.index') }}"
                   class="text-xs sm:text-sm text-blue-600 hover:text-blue-800">
                    View all →
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4">
                <div class="rounded-lg border-l-4 border-purple-500 bg-purple-50 dark:bg-purple-900/20 p-3 sm:p-4">
                    <p class="text-xs text-gray-500 mb-1">Loans Taken ({{ $year }})</p>
                    <p class="text-lg sm:text-xl font-bold text-purple-600">
                        {{ number_format($loanStats['disbursed']) }}
                    </p>
                </div>

                <div class="rounded-lg border-l-4 border-orange-500 bg-orange-50 dark:bg-orange-900/20 p-3 sm:p-4">
                    <p class="text-xs text-gray-500 mb-1">Loan Payments ({{ $year }})</p>
                    <p class="text-lg sm:text-xl font-bold text-orange-600">
                        {{ number_format($loanStats['payments']) }}
                    </p>
                </div>

                <div class="rounded-lg border-l-4 border-red-500 bg-red-50 dark:bg-red-900/20 p-3 sm:p-4">
                    <p class="text-xs text-gray-500 mb-1">Active Loan Balance</p>
                    <p class="text-lg sm:text-xl font-bold text-red-600">
                        {{ number_format($loanStats['active_balance']) }}
                    </p>
                </div>
            </div>

            <div class="rounded-md border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 p-3 text-xs text-yellow-800 dark:text-yellow-200">
                <strong>Note:</strong> Loans are not income. Repayments and interest are included as expenses.
            </div>
        </section>

    </div>

    {{-- Modal for All Years --}}
    <div
        x-data="{ open: false }"
        @open-all-years-modal.window="open = true"
        x-show="open"
        x-cloak
        class="fixed inset-0 z-50 overflow-y-auto"
        style="display: none;"
    >
        <div class="flex items-center justify-center min-h-screen px-4">
            {{-- Backdrop --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                @click="open = false"
                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"
            ></div>

            {{-- Modal Content --}}
            <div
                x-show="open"
                x-transition:enter="ease-out duration-300"
                x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave="ease-in duration-200"
                x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                class="relative bg-white dark:bg-gray-800 rounded-lg shadow-xl max-w-md w-full"
            >
                <div class="p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                            Select Year
                        </h3>
                        <button
                            @click="open = false"
                            class="text-gray-400 hover:text-gray-500"
                        >
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-2 max-h-96 overflow-y-auto">
                        @for($y = $maxYear; $y >= $minYear; $y--)
                            <a
                                href="{{ url('budgets/' . $y) }}"
                                class="flex items-center justify-center px-4 py-3 text-sm font-medium rounded-lg border transition {{ $y == $year ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600' }}"
                            >
                                {{ $y }}
                            </a>
                        @endfor
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Hide scrollbar for month selector on mobile */
        .scrollbar-hide::-webkit-scrollbar {
            display: none;
        }
        .scrollbar-hide {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>

    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
