<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Client Funds Management') }}
            </h2>
            <a href="{{ route('client-funds.create') }}"
               class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm text-center">
                + Record Client Fund
            </a>
        </div>
    </x-slot>

    <div class="py-3 sm:py-8 lg:py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="bg-green-100 text-green-700 p-3 rounded mb-4 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 text-red-700 p-3 rounded mb-4 text-sm">
                    {{ session('error') }}
                </div>
            @endif

            <!-- Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Received</p>
                    <p class="text-lg sm:text-xl font-bold text-blue-600">
                        KES {{ number_format($summary['total_received'], 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Total Spent</p>
                    <p class="text-lg sm:text-xl font-bold text-orange-600">
                        KES {{ number_format($summary['total_spent'], 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Your Profit</p>
                    <p class="text-lg sm:text-xl font-bold text-green-600">
                        KES {{ number_format($summary['total_profit'], 0, '.', ',') }}
                    </p>
                </div>

                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Pending Balance</p>
                    <p class="text-lg sm:text-xl font-bold text-purple-600">
                        KES {{ number_format($summary['total_balance'], 0, '.', ',') }}
                    </p>
                </div>
            </div>

            <!-- Client Funds List -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                    <h3 class="text-base sm:text-lg font-semibold">All Client Funds</h3>
                </div>

                @forelse($clientFunds as $fund)
                    <div class="p-3 sm:p-4 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                        <div class="flex flex-col gap-3">
                            <!-- Top Section: Name and Badges -->
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2 mb-2">
                                    <h4 class="font-semibold text-sm sm:text-base text-gray-800 dark:text-gray-200">
                                        {{ $fund->client_name }}
                                    </h4>
                                    <span class="px-2 py-1 text-xs rounded-full whitespace-nowrap
                                        {{ $fund->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                        {{ $fund->status === 'pending' ? 'bg-yellow-100 text-yellow-700' : '' }}
                                        {{ $fund->status === 'partial' ? 'bg-blue-100 text-blue-700' : '' }}">
                                        {{ ucfirst($fund->status) }}
                                    </span>
                                    <span class="px-2 py-1 text-xs rounded-full whitespace-nowrap
                                        {{ $fund->type === 'commission' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $fund->type === 'commission' ? '💰 Profit' : '🔄 No Profit' }}
                                    </span>
                                </div>
                                <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-1">{{ $fund->purpose }}</p>
                                <p class="text-xs text-gray-500">
                                    {{ $fund->received_date->format('M d, Y') }} • {{ $fund->account->name }}
                                </p>
                            </div>

                            <!-- Financial Summary -->
                            <div class="grid grid-cols-2 sm:flex sm:flex-wrap gap-x-4 gap-y-2 text-xs sm:text-sm">
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Received:</span>
                                    <span class="font-semibold ml-1">KES {{ number_format($fund->amount_received, 0, '.', ',') }}</span>
                                </div>
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Spent:</span>
                                    <span class="font-semibold text-orange-600 ml-1">KES {{ number_format($fund->amount_spent, 0, '.', ',') }}</span>
                                </div>
                                @if($fund->type === 'commission')
                                    <div>
                                        <span class="text-gray-600 dark:text-gray-400">Profit:</span>
                                        <span class="font-semibold text-green-600 ml-1">KES {{ number_format($fund->profit_amount, 0, '.', ',') }}</span>
                                    </div>
                                @endif
                                <div>
                                    <span class="text-gray-600 dark:text-gray-400">Balance:</span>
                                    <span class="font-bold text-purple-600 ml-1">KES {{ number_format($fund->balance, 0, '.', ',') }}</span>
                                </div>
                            </div>

                            <!-- Action Button -->
                            <div>
                                <a href="{{ route('client-funds.show', $fund) }}"
                                   class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-xs sm:text-sm text-center w-full sm:w-auto">
                                    View Details
                                </a>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-8 text-center">
                        <p class="text-gray-500 text-sm mb-4">No client funds recorded yet.</p>
                        <a href="{{ route('client-funds.create') }}"
                           class="inline-block bg-indigo-600 text-white px-6 py-2 rounded text-sm">
                            Record Your First Client Fund
                        </a>
                    </div>
                @endforelse
            </div>

        </div>
    </div>
    <x-floating-action-button :quickAccount="$allAccounts->first()" />
</x-app-layout>
