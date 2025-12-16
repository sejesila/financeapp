<x-app-layout>
    {{-- Header --}}
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
                    Transactions
                </h2>
                <p class="text-sm text-gray-500">
                    View and manage all income & expenses
                </p>
            </div>

            <a href="{{ route('transactions.create') }}"
               class="inline-flex items-center gap-2 rounded-md bg-indigo-600 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-700">
                + New Transaction
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-7xl py-8 space-y-6">

        {{-- Flash Messages --}}
        @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
            @if(session($key))
                <div class="rounded-md bg-{{ $color }}-100 px-4 py-3 text-{{ $color }}-700">
                    {{ session($key) }}
                </div>
            @endif
        @endforeach

        {{-- Summary Cards (colored as original) --}}
        <div class="grid grid-cols-1 md:grid-cols-7 gap-3 mb-6">
            <div class="bg-blue-100 p-3 rounded-lg border border-blue-300">
                <h3 class="text-xs font-semibold text-blue-800 mb-1">Today</h3>
                <p class="text-xl font-bold text-blue-900">{{ number_format($totalToday, 0, '.', ',') }}</p>
            </div>

            <div class="bg-orange-100 p-3 rounded-lg border border-orange-300">
                <h3 class="text-xs font-semibold text-orange-800 mb-1">Yesterday</h3>
                <p class="text-xl font-bold text-orange-900">{{ number_format($totalYesterday, 0, '.', ',') }}</p>
            </div>

            <div class="bg-green-100 p-3 rounded-lg border border-green-300">
                <h3 class="text-xs font-semibold text-green-800 mb-1">This Week</h3>
                <p class="text-xl font-bold text-green-900">{{ number_format($totalThisWeek, 0, '.', ',') }}</p>
            </div>

            <div class="bg-yellow-100 p-3 rounded-lg border border-yellow-300">
                <h3 class="text-xs font-semibold text-yellow-800 mb-1">Last Week</h3>
                <p class="text-xl font-bold text-yellow-900">{{ number_format($totalLastWeek, 0, '.', ',') }}</p>
            </div>

            <div class="bg-teal-100 p-3 rounded-lg border border-teal-300">
                <h3 class="text-xs font-semibold text-teal-800 mb-1">This Month</h3>
                <p class="text-xl font-bold text-teal-900">{{ number_format($totalThisMonth, 0, '.', ',') }}</p>
            </div>

            <div class="bg-pink-100 p-3 rounded-lg border border-pink-300">
                <h3 class="text-xs font-semibold text-pink-800 mb-1">Last Month</h3>
                <p class="text-xl font-bold text-pink-900">{{ number_format($totalLastMonth, 0, '.', ',') }}</p>
            </div>

            <div class="bg-purple-100 p-3 rounded-lg border border-purple-300">
                <h3 class="text-xs font-semibold text-purple-800 mb-1">All Time</h3>
                <p class="text-xl font-bold text-purple-900">{{ number_format($totalAll, 0, '.', ',') }}</p>
            </div>
        </div>

        {{-- Filters --}}
        <div class="rounded-lg border bg-white p-4 shadow-sm space-y-4">

            {{-- Quick Filters --}}
            <div class="flex flex-wrap gap-2">
                @foreach([
                    'all' => 'All',
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'this_week' => 'This Week',
                    'last_week' => 'Last Week',
                    'this_month' => 'This Month',
                    'last_month' => 'Last Month',
                    'this_year' => 'This Year',
                ] as $key => $label)
                    <a href="{{ route('transactions.index', ['filter' => $key]) }}"
                       class="px-4 py-2 rounded-md text-sm
                       {{ $filter === $key
                            ? 'bg-indigo-600 text-white'
                            : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>

            {{-- Advanced Filters --}}
            <details {{ $filter === 'custom' || $search || $categoryId || $accountId ? 'open' : '' }}>
                <summary class="cursor-pointer text-sm font-medium text-gray-700">
                    Advanced Filters
                </summary>

                <form method="GET"
                      action="{{ route('transactions.index') }}"
                      class="mt-4 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                    <input type="hidden" name="filter" value="custom">

                    <div>
                        <label class="block text-xs font-medium mb-1">Search</label>
                        <input type="text" name="search" value="{{ $search }}"
                               class="w-full rounded-md border-gray-300 text-sm">
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Category</label>
                        <select name="category_id"
                                class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" @selected($categoryId == $category->id)>
                                    {{ $category->name }} ({{ ucfirst($category->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Account</label>
                        <select name="account_id"
                                class="w-full rounded-md border-gray-300 text-sm">
                            <option value="">All</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected($accountId == $account->id)>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-medium mb-1">Date Range</label>
                        <div class="flex gap-2">
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                   class="w-full rounded-md border-gray-300 text-sm">
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                   class="w-full rounded-md border-gray-300 text-sm">
                        </div>
                    </div>

                    <div class="col-span-full flex gap-2">
                        <button class="rounded-md bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-700">
                            Apply
                        </button>
                        <a href="{{ route('transactions.index') }}"
                           class="rounded-md bg-gray-200 px-4 py-2 text-sm hover:bg-gray-300">
                            Reset
                        </a>
                    </div>
                </form>
            </details>
        </div>

        {{-- Transactions Table --}}
        <div class="overflow-x-auto rounded-lg border bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                <tr>
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Description</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2 text-left">Account</th>
                    <th class="px-3 py-2 text-left">Category</th>
                    <th class="px-3 py-2 text-center">Action</th>
                </tr>
                </thead>

                <tbody class="divide-y">
                @forelse($transactions as $t)
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2">
                            {{ \Carbon\Carbon::parse($t->date)->format('M d, Y') }}
                        </td>

                        <td class="px-3 py-2">{{ $t->description }}</td>

                        <td class="px-3 py-2 text-right font-semibold
                                {{ $t->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $t->category->type === 'income' ? '+' : '-' }}
                            {{ number_format($t->amount) }}
                        </td>

                        <td class="px-3 py-2">{{ $t->account->name ?? '—' }}</td>

                        <td class="px-3 py-2">
                                <span class="inline-flex rounded-full px-2 py-1 text-xs
                                    {{ $t->category->type === 'income'
                                        ? 'bg-green-100 text-green-800'
                                        : 'bg-red-100 text-red-800' }}">
                                    {{ $t->category->name }}
                                </span>
                        </td>

                        <td class="px-3 py-2 text-center">
                            <a href="{{ route('transactions.show', $t) }}"
                               class="text-indigo-600 hover:underline text-sm">
                                View
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                            No transactions found.
                            <a href="{{ route('transactions.create') }}"
                               class="text-indigo-600 hover:underline ml-1">
                                Add one
                            </a>
                        </td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Filtered Total --}}
        @if($filter !== 'all')
            <div class="rounded-lg bg-gray-50 p-4">
                <p class="font-semibold">
                    Filtered Total:
                    <span class="text-indigo-600">
                        {{ number_format($transactions->sum('amount')) }}
                    </span>
                </p>
            </div>
        @endif

        {{-- Pagination --}}
        <div class="pt-4 border-t">
            {{ $transactions->links() }}
        </div>

    </div>
</x-app-layout>
