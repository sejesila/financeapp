<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Loans') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Filters -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    Loans are automatically created when you top up your account via M-Pesa
                </p>

                <form method="GET" action="{{ route('loans.index') }}" class="flex flex-wrap gap-2 items-center">
                    <div class="relative">
                        <select name="year"
                                class="block appearance-none w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 p-2 pl-3 pr-8 rounded text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="">All Years</option>
                            @for($y = $minYear; $y <= $maxYear; $y++)
                                <option value="{{ $y }}" @selected($year == $y)>{{ $y }}</option>
                            @endfor
                        </select>
                    </div>

                    <div class="relative">
                        <select name="filter"
                                class="block appearance-none w-full border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 p-2 pl-3 pr-8 rounded text-sm bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500">
                            <option value="active" @selected($filter == 'active')>Active Loans</option>
                            <option value="all_paid" @selected($filter == 'all_paid')>All Paid Loans</option>
                        </select>
                    </div>

                    <button type="submit"
                            class="bg-blue-600 text-white px-4 py-2 rounded text-sm hover:bg-blue-700">
                        Filter
                    </button>

                    @if($year || $filter != 'active')
                        <a href="{{ route('loans.index') }}"
                           class="text-gray-600 dark:text-gray-400 text-sm hover:text-gray-800 dark:hover:text-gray-200">
                            Clear
                        </a>
                    @endif
                </form>
            </div>

            <!-- Flash Messages -->
            @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="rounded-md bg-{{ $color }}-100 dark:bg-{{ $color }}-900/20 px-4 py-3 text-{{ $color }}-700 dark:text-{{ $color }}-300">
                        {{ session($key) }}
                    </div>
                @endif
            @endforeach

            <!-- Summary Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
                <div class="bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Total Borrowed (Active)</h3>
                    <p class="text-3xl font-bold text-blue-700 dark:text-blue-400">
                        KES {{ number_format($activeLoans->sum('principal_amount'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $activeLoans->count() }} active loans</p>
                </div>

                <div class="bg-red-50 dark:bg-red-900/20 border-l-4 border-red-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Total Outstanding</h3>
                    <p class="text-3xl font-bold text-red-700 dark:text-red-400">
                        KES {{ number_format($activeLoans->sum('balance'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Remaining to repay</p>
                </div>

                <div class="bg-green-50 dark:bg-green-900/20 border-l-4 border-green-500 p-6 rounded">
                    <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300 mb-2">Total Repaid (All Time)</h3>
                    <p class="text-3xl font-bold text-green-700 dark:text-green-400">
                        KES {{ number_format($paidLoans->sum('amount_paid'), 0, '.', ',') }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">All loans combined</p>
                </div>
            </div>

            <!-- Active Loans Table -->
            <div>
                <h2 class="text-2xl font-bold mb-4 text-gray-800 dark:text-gray-200">Active Loans</h2>
                @if($activeLoans->count() > 0)
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Principal</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Due</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Paid</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Balance</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Disbursed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Due Date</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($activeLoans as $loan)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ $loan->source }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $loan->account->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                        @php
                                            $loanStyles = match($loan->loan_type) {
                                                'kcb_mpesa' => 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300',
                                                'other'     => 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300',
                                                default     => 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300', // M-Shwari
                                            };

                                            $loanLabel = match($loan->loan_type) {
                                                'kcb_mpesa' => 'KCB M-Pesa',
                                                'other'     => 'Other',
                                                default     => 'M-Shwari',
                                            };
                                        @endphp

                                        <span class="px-2 py-1 text-xs rounded-full {{ $loanStyles }}">
    {{ $loanLabel }}
</span>

                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 font-medium">{{ number_format($loan->principal_amount, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($loan->total_amount, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400">{{ number_format($loan->amount_paid, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right text-red-600 dark:text-red-400 font-semibold">{{ number_format($loan->balance, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($loan->disbursed_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        @if($loan->due_date)
                                            @php $isOverdue = \Carbon\Carbon::parse($loan->due_date)->isPast(); @endphp
                                            <span class="{{ $isOverdue ? 'text-red-600 font-semibold' : '' }}">
                                                {{ \Carbon\Carbon::parse($loan->due_date)->format('M d, Y') }}
                                            </span>
                                            @if($isOverdue)
                                                <span class="block text-xs text-red-500">Overdue</span>
                                            @endif
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2 flex-wrap">
                                            <a href="{{ route('loans.show', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition">View</a>
                                            <a href="{{ route('loans.payment', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-xs font-medium rounded hover:bg-green-700 transition">Pay</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-8 text-center">
                        <p class="text-gray-500 dark:text-gray-400 text-lg">No active loans</p>
                        <p class="text-gray-600 dark:text-gray-500 text-sm mt-2">Loans are created when you top up your account using M-Pesa</p>
                    </div>
                @endif
            </div>

            <!-- Paid Loans Table -->
            @if($paidLoans->count() > 0)
                <div>
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-2">
                        <h2 class="text-2xl font-bold text-gray-800 dark:text-gray-200">
                            @if($filter === 'all_paid' || $year)
                                Paid Loans @if($year) ({{ $year }}) @endif
                            @else
                                Recently Paid Loans
                            @endif
                        </h2>
                        @if($filter !== 'all_paid' && !$year)
                            <a href="{{ route('loans.index', ['filter' => 'all_paid']) }}"
                               class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 text-sm">
                                View All Paid Loans →
                            </a>
                        @endif
                    </div>
                    <div class="bg-white dark:bg-gray-800 shadow rounded-lg overflow-x-auto">
                        <table class="min-w-full text-sm divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Account</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Principal</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Due</th>
                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Paid</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Disbursed</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Repaid</th>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                            </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @foreach($paidLoans as $loan)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ $loan->source }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ $loan->account->name ?? 'N/A' }}</td>
                                    <td class="px-4 py-3">
                                            <span class="px-2 py-1 text-xs rounded-full
                                                {{ $loan->loan_type === 'kcb_mpesa' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-300' }}">
                                                {{ $loan->loan_type === 'kcb_mpesa' ? 'KCB M-Pesa' : 'M-Shwari' }}
                                            </span>
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100 font-medium">{{ number_format($loan->principal_amount, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-900 dark:text-gray-100">{{ number_format($loan->total_amount, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-right text-green-600 dark:text-green-400 font-semibold">{{ number_format($loan->amount_paid, 0, '.', ',') }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">{{ \Carbon\Carbon::parse($loan->disbursed_date)->format('M d, Y') }}</td>
                                    <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                        @if($loan->repaid_date)
                                            {{ \Carbon\Carbon::parse($loan->repaid_date)->format('M d, Y') }}
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <a href="{{ route('loans.show', $loan->id) }}" class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded hover:bg-blue-700 transition">View</a>
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
    <x-floating-action-button :quickAccount="$accounts->first()" />
</x-app-layout>
