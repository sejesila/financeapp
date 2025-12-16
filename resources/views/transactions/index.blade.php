@extends('layout')

@section('content')
    <div class="flex items-center justify-between mb-4">
        <h2 class="text-2xl font-semibold">All Transactions</h2>

        <a href="{{ route('transactions.create') }}"
           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
            + Add New Transaction
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-100 text-green-700 p-4 rounded mb-4">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
            {{ session('error') }}
        </div>
    @endif

    <!-- Summary Cards -->
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

    <!-- Filter Section -->
    <div class="bg-white p-4 rounded-lg shadow mb-4">
        <form method="GET" action="{{ route('transactions.index') }}" class="space-y-4">
            <!-- Quick Filters -->
            <div class="flex flex-wrap gap-2">
                <button type="submit" name="filter" value="all"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    All
                </button>
                <button type="submit" name="filter" value="today"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'today' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Today
                </button>
                <button type="submit" name="filter" value="yesterday"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'yesterday' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Yesterday
                </button>
                <button type="submit" name="filter" value="this_week"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'this_week' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    This Week
                </button>
                <button type="submit" name="filter" value="last_week"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'last_week' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Last Week
                </button>
                <button type="submit" name="filter" value="this_month"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'this_month' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    This Month
                </button>
                <button type="submit" name="filter" value="last_month"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'last_month' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    Last Month
                </button>
                <button type="submit" name="filter" value="this_year"
                        class="px-4 py-2 rounded text-sm {{ $filter == 'this_year' ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                    This Year
                </button>
            </div>

            <!-- Advanced Filters -->
            <details class="border-t pt-3" {{ $filter == 'custom' || $search || $categoryId || $accountId ? 'open' : '' }}>
                <summary class="cursor-pointer text-sm font-semibold text-gray-700 hover:text-gray-900">
                    Advanced Filters
                </summary>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-3 mt-3">
                    <!-- Search -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Search Description</label>
                        <input type="text" name="search" value="{{ $search }}"
                               placeholder="Search transactions..."
                               class="w-full border rounded p-2 text-sm">
                    </div>

                    <!-- Category Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Category</label>
                        <select name="category_id" class="w-full border rounded p-2 text-sm">
                            <option value="">All Categories</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}" {{ $categoryId == $category->id ? 'selected' : '' }}>
                                    {{ $category->name }} ({{ ucfirst($category->type) }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Account Filter -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Account</label>
                        <select name="account_id" class="w-full border rounded p-2 text-sm">
                            <option value="">All Accounts</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" {{ $accountId == $account->id ? 'selected' : '' }}>
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Custom Date Range -->
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Date Range</label>
                        <div class="flex gap-1">
                            <input type="date" name="start_date" value="{{ $startDate }}"
                                   class="w-full border rounded p-2 text-sm">
                            <input type="date" name="end_date" value="{{ $endDate }}"
                                   class="w-full border rounded p-2 text-sm">
                        </div>
                    </div>
                </div>

                <div class="flex gap-2 mt-3">
                    <button type="submit" name="filter" value="custom"
                            class="bg-indigo-600 text-white px-4 py-2 rounded text-sm hover:bg-indigo-700">
                        Apply Filters
                    </button>
                    <a href="{{ route('transactions.index') }}"
                       class="bg-gray-300 text-gray-700 px-4 py-2 rounded text-sm hover:bg-gray-400">
                        Clear All
                    </a>
                </div>
            </details>
        </form>
    </div>

    <!-- Transactions Table -->
    <div class="overflow-x-auto">
        <table class="w-full border-collapse mt-4">
            <thead>
            <tr class="bg-gray-200 text-left">
                <th class="p-2 border">Date</th>
                <th class="p-2 border">Description</th>
                <th class="p-2 border">Amount</th>
                <th class="p-2 border">Account</th>
                <th class="p-2 border">Category</th>
                <th class="p-2 border text-center">Action</th>
            </tr>
            </thead>

            <tbody>
            @forelse ($transactions as $t)
                <tr class="hover:bg-gray-50">
                    <td class="p-2 border">{{ \Carbon\Carbon::parse($t->date)->format('M d, Y') }}</td>
                    <td class="p-2 border">{{ $t->description }}</td>
                    <td class="p-2 border">
                        <span class="font-semibold {{ $t->category->type === 'income' ? 'text-green-600' : 'text-red-600' }}">
                            {{ $t->category->type === 'income' ? '+' : '-' }}{{ number_format($t->amount, 0, '.', ',') }}
                        </span>
                    </td>
                    <td class="p-2 border">{{ $t->account->name ?? 'N/A' }}</td>
                    <td class="p-2 border">
                        <span class="px-2 py-1 rounded text-xs {{ $t->category->type === 'income' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $t->category->name }}
                        </span>
                    </td>
                    <td class="p-2 border text-center">
                        <a href="{{ route('transactions.show', $t) }}"
                           class="bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600 text-sm inline-block">
                            View Details
                        </a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="p-4 text-center text-gray-500">
                        No transactions found. <a href="{{ route('transactions.create') }}" class="text-indigo-600 hover:underline">Add your first transaction</a>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <!-- Filtered Total -->
    @if($filter != 'all')
        <div class="mt-4 p-4 bg-gray-100 rounded-lg">
            <p class="text-lg font-semibold">
                Total for
                @if($filter == 'today') Today @endif
                @if($filter == 'yesterday') Yesterday @endif
                @if($filter == 'this_week') This Week @endif
                @if($filter == 'last_week') Last Week @endif
                :
                <span class="text-indigo-600">
                    {{ number_format($transactions->sum('amount'), 0, '.', ',') }}
                </span>
            </p>
        </div>
    @endif

    <!-- Pagination -->
    <div class="mt-6">
        {{ $transactions->links() }}
    </div>
@endsection
