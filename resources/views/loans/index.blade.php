<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Loans') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-6">
                <div class="flex items-center justify-between mb-6">
                    <div>
                        <h1 class="text-3xl font-bold">Loans</h1>
                        <p class="text-gray-600 text-sm mt-1">Loans are automatically created when you top up your account via M-Pesa</p>
                    </div>

                    <!-- Filter Controls -->
                    <form method="GET" action="{{ route('loans.index') }}" class="flex items-center gap-2">
                        <select name="year" class="border border-gray-300 p-2 rounded text-sm">
                            <option value="">All Years</option>
                            @for($y = $minYear; $y <= $maxYear; $y++)
                                <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                            @endfor
                        </select>

                        <select name="filter" class="border border-gray-300 p-2 rounded text-sm">
                            <option value="active" {{ $filter == 'active' ? 'selected' : '' }}>Active Loans</option>
                            <option value="all_paid" {{ $filter == 'all_paid' ? 'selected' : '' }}>All Paid Loans</option>
                        </select>

                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                            Filter
                        </button>

                        @if($year || $filter != 'active')
                            <a href="{{ route('loans.index') }}" class="text-gray-600 text-sm hover:text-gray-800">
                                Clear
                            </a>
                        @endif
                    </form>
                </div>

                @if(session('success'))
                    <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                        {{ session('error') }}
                    </div>
                @endif
            </div>

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
                <div class="bg-blue-50 border-l-4 border-blue-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Borrowed (Active)</h3>
                    <p class="text-3xl font-bold text-blue-700">
                        KES {{ number_format($activeLoans->sum('principal_amount'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">{{ $activeLoans->count() }} active loans</p>
                </div>

                <div class="bg-red-50 border-l-4 border-red-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Outstanding</h3>
                    <p class="text-3xl font-bold text-red-700">
                        KES {{ number_format($activeLoans->sum('balance'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">Remaining to repay</p>
                </div>

                <div class="bg-green-50 border-l-4 border-green-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 mb-2">Total Repaid (All Time)</h3>
                    <p class="text-3xl font-bold text-green-700">
                        KES {{ number_format($paidLoans->sum('amount_paid'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 mt-1">All loans combined</p>
                </div>
            </div>

            <!-- Active Loans -->
            <div class="mb-8">
                <h2 class="text-2xl font-bold mb-4">Active Loans</h2>

                @if($activeLoans->count() > 0)
                    <div class="bg-white shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Account</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Principal</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Interest</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Due</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Paid</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Balance</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Due Date</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach($activeLoans as $loan)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-semibold text-gray-900">{{ $loan->source }}</div>
                                        <div class="text-xs text-gray-500">{{ $loan->disbursed_date->format('M d, Y') }}</div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-700">
                                        {{ $loan->account->name }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-900">
                                        KES {{ number_format($loan->principal_amount, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-900">
                                        @if($loan->interest_amount > 0)
                                            KES {{ number_format($loan->interest_amount, 0, '.', ',') }}
                                            @if($loan->interest_rate > 0)
                                                <div class="text-xs text-gray-500">({{ $loan->interest_rate }}%)</div>
                                            @endif
                                        @else
                                            <span class="text-gray-400">-</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right text-gray-900 font-semibold">
                                        KES {{ number_format($loan->total_amount, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-green-600 font-semibold">
                                        KES {{ number_format($loan->amount_paid, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4 text-right text-red-600 font-bold">
                                        KES {{ number_format($loan->balance, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($loan->due_date)
                                            <div class="text-sm">
                                                {{ $loan->due_date->format('M d') }}
                                            </div>
                                            @php
                                                $now = now();
                                                $dueDate = $loan->due_date;
                                                $isOverdue = $now->isAfter($dueDate);
                                                $daysDiff = round(abs($now->diffInDays($dueDate)));
                                            @endphp
                                            <div class="text-xs {{ $isOverdue ? 'text-red-600 font-semibold' : ($daysDiff < 7 ? 'text-yellow-600' : 'text-gray-500') }}">
                                                @if($isOverdue)
                                                    {{ $daysDiff }}d overdue
                                                @elseif($daysDiff == 0)
                                                    Due today
                                                @else
                                                    {{ $daysDiff }}d left
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400 text-sm">No due date</span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 text-right space-x-2">
                                        <a href="{{ route('loans.show', $loan) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            View
                                        </a>
                                        <a href="{{ route('loans.payment', $loan) }}" class="text-green-600 hover:text-green-800 text-sm font-semibold">
                                            Pay
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-8 text-center">
                        <p class="text-gray-500 text-lg">No active loans</p>
                        <p class="text-gray-600 text-sm mt-2">Loans are created when you top up your account using M-Pesa</p>
                    </div>
                @endif
            </div>

            <!-- Recently Paid Loans -->
            @if($paidLoans->count() > 0)
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <h2 class="text-2xl font-bold">
                            @if($filter === 'all_paid' || $year)
                                Paid Loans
                                @if($year)
                                    ({{ $year }})
                                @endif
                            @else
                                Recently Paid Loans
                            @endif
                        </h2>
                        @if($filter !== 'all_paid' && !$year)
                            <a href="{{ route('loans.index', ['filter' => 'all_paid']) }}" class="text-blue-600 hover:text-blue-800 text-sm">
                                View All Paid Loans →
                            </a>
                        @endif
                    </div>
                    <div class="bg-white shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-100">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Source</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Account</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Principal</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Total Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Disbursed</th>
                                <th class="px-6 py-3 text-left text-xs font-semibold text-gray-600 uppercase">Paid Off</th>
                                <th class="px-6 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                            @foreach($paidLoans as $loan)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 font-semibold text-gray-700">{{ $loan->source }}</td>
                                    <td class="px-6 py-4 text-gray-700">{{ $loan->account->name }}</td>
                                    <td class="px-6 py-4 text-right text-gray-900">
                                        KES {{ number_format($loan->principal_amount, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4 text-right font-semibold text-green-600">
                                        KES {{ number_format($loan->total_amount, 0, '.', ',') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $loan->disbursed_date->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-sm text-gray-600">
                                        {{ $loan->repaid_date ? $loan->repaid_date->format('M d, Y') : $loan->updated_at->format('M d, Y') }}
                                    </td>
                                    <td class="px-6 py-4 text-right">
                                        <a href="{{ route('loans.show', $loan) }}" class="text-blue-600 hover:text-blue-800 text-sm font-medium">
                                            View
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
