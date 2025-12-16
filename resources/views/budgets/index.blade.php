<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Budget Overview
                </h2>
                <p class="text-sm text-gray-500">
                    {{ $year }} annual budget performance
                </p>
            </div>

            <select onchange="location.href='{{ url('budgets') }}/' + this.value"
                    class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                @for($y = $minYear; $y <= $maxYear; $y++)
                    <option value="{{ $y }}" @selected($y === $year)>
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

        {{-- Budget Usage Summary --}}
        <section class="space-y-4">
            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">
                Budget Usage Summary
            </h3>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($expenseCategories as $category)
                    @php
                        $p = $category->budget_percentage;
                        $state = $p >= 100 ? 'red' : ($p >= 80 ? 'yellow' : ($p >= 60 ? 'blue' : 'green'));
                    @endphp

                    <div class="rounded-lg border-l-4 p-4
                        {{ $state === 'red' ? 'border-red-500 bg-red-50 dark:bg-red-900/20' : '' }}
                        {{ $state === 'yellow' ? 'border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20' : '' }}
                        {{ $state === 'blue' ? 'border-blue-500 bg-blue-50 dark:bg-blue-900/20' : '' }}
                        {{ $state === 'green' ? 'border-green-500 bg-green-50 dark:bg-green-900/20' : '' }}
                    ">
                        <div class="flex justify-between items-center mb-2">
                            <h4 class="font-medium text-gray-800 dark:text-gray-200">
                                {{ $category->name }}
                            </h4>
                            <span class="text-xs font-semibold
                                {{ $state === 'red' ? 'text-red-600' : '' }}
                                {{ $state === 'yellow' ? 'text-yellow-600' : '' }}
                                {{ $state === 'blue' ? 'text-blue-600' : '' }}
                                {{ $state === 'green' ? 'text-green-600' : '' }}
                            ">
                                {{ $p }}%
                            </span>
                        </div>

                        <div class="text-xs text-gray-500 mb-1 flex justify-between">
                            <span>{{ number_format($category->yearly_total) }}</span>
                            <span>{{ number_format($category->yearly_budget) }}</span>
                        </div>

                        <div class="h-2 rounded bg-gray-200 dark:bg-gray-700">
                            <div class="h-2 rounded
                                {{ $state === 'red' ? 'bg-red-500' : '' }}
                                {{ $state === 'yellow' ? 'bg-yellow-500' : '' }}
                                {{ $state === 'blue' ? 'bg-blue-500' : '' }}
                                {{ $state === 'green' ? 'bg-green-500' : '' }}
                            "
                                 style="width: {{ min($p, 100) }}%"></div>
                        </div>

                        @if($p >= 100)
                            <p class="mt-1 text-xs font-semibold text-red-600">
                                Over budget
                            </p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Budget Table --}}
        <form method="POST" action="{{ route('budgets.update') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="year" value="{{ $year }}">

            <div class="flex justify-end">
                <button class="rounded-md bg-indigo-600 px-6 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                    Save Budgets
                </button>
            </div>

            <div class="overflow-x-auto rounded-lg border bg-white dark:bg-gray-800 shadow-sm">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 dark:bg-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left w-44">Category</th>
                        @for($m = 1; $m <= 12; $m++)
                            <th class="px-2 py-2 text-center">
                                {{ \Carbon\Carbon::create()->month($m)->format('M') }}
                            </th>
                        @endfor
                        <th class="px-3 py-2 text-center bg-indigo-100 dark:bg-indigo-900">
                            Total
                        </th>
                    </tr>
                    </thead>

                    <tbody>
                    {{-- INCOME --}}
                    <tr class="bg-green-100 dark:bg-green-900">
                        <td colspan="14" class="p-2 font-bold">INCOME</td>
                    </tr>

                    @foreach($incomeCategories as $category)
                        {{-- your existing income rows --}}
                    @endforeach

                    {{-- TOTAL INCOME --}}
                    <tr class="bg-green-200 font-bold">
                        {{-- totals --}}
                    </tr>

                    {{-- EXPENSES --}}
                    <tr class="bg-red-100 dark:bg-red-900">
                        <td colspan="14" class="p-2 font-bold">EXPENSES</td>
                    </tr>

                    @foreach($expenseCategories as $category)
                        {{-- your existing expense rows --}}
                    @endforeach

                    {{-- TOTAL EXPENSES --}}
                    <tr class="bg-red-200 font-bold">
                        {{-- totals --}}
                    </tr>

                    {{-- NET --}}
                    <tr class="bg-blue-200 font-bold">
                        {{-- net --}}
                    </tr>
                    </tbody>

                </table>
            </div>
        </form>

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

            <div class="rounded-md border-l-4 border-yellow-500 bg-yellow-50 dark:bg-yellow-900/20 p-3 text-xs text-yellow-800">
                <strong>Note:</strong> Loans are not income. Repayments and interest are included as expenses.
            </div>
        </section>

    </div>
</x-app-layout>
