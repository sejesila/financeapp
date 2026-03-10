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

            {{-- Flash Messages --}}
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

            {{-- Summary Cards --}}
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 sm:gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        {{ $clientFilter ? 'Received' : 'Total Received' }}
                    </p>
                    <p class="text-lg sm:text-xl font-bold text-blue-600">
                        KES {{ number_format($summary['total_received'], 0, '.', ',') }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        {{ $clientFilter ? 'Spent' : 'Total Spent' }}
                    </p>
                    <p class="text-lg sm:text-xl font-bold text-orange-600">
                        KES {{ number_format($summary['total_spent'], 0, '.', ',') }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        {{ $clientFilter ? 'Your Profit' : 'Total Profit' }}
                    </p>
                    <p class="text-lg sm:text-xl font-bold text-green-600">
                        KES {{ number_format($summary['total_profit'], 0, '.', ',') }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 p-3 sm:p-4 rounded-lg shadow">
                    <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">
                        {{ $clientFilter ? 'Pending Balance' : 'Total Pending' }}
                    </p>
                    <p class="text-lg sm:text-xl font-bold text-purple-600">
                        KES {{ number_format($summary['total_balance'], 0, '.', ',') }}
                    </p>
                </div>
            </div>

            {{-- Per-Client Summary (only when not filtering) --}}
            @if(!$clientFilter && $clientTotals->count() > 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden mb-6">
                    <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Per-Client Summary
                        </h3>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $clientTotals->count() }} {{ Str::plural('client', $clientTotals->count()) }}
                        </span>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium">Client</th>
                                <th class="px-4 py-3 text-center font-medium hidden sm:table-cell">Entries</th>
                                <th class="px-4 py-3 text-right font-medium">Received</th>
                                <th class="px-4 py-3 text-right font-medium hidden md:table-cell">Spent</th>
                                <th class="px-4 py-3 text-right font-medium hidden md:table-cell">Profit</th>
                                <th class="px-4 py-3 text-right font-medium">Pending</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                            @foreach($clientTotals as $client)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                    <td class="px-4 py-3">
                                        <a href="{{ route('client-funds.index', ['client' => $client->client_name]) }}"
                                           class="font-semibold text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-200 hover:underline">
                                            {{ $client->client_name }}
                                        </a>
                                        {{-- Mobile: show spent/profit inline --}}
                                        <div class="md:hidden text-xs text-gray-500 mt-0.5">
                                            Spent: <span class="text-orange-600 font-medium">{{ number_format($client->total_spent, 0) }}</span>
                                            @if($client->total_profit > 0)
                                                · Profit: <span class="text-green-600 font-medium">{{ number_format($client->total_profit, 0) }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-center hidden sm:table-cell">
                                            <span class="inline-flex items-center justify-center w-6 h-6 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 text-xs font-semibold">
                                                {{ $client->total_entries }}
                                            </span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                                        {{ number_format($client->total_received, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right text-orange-600 dark:text-orange-400 whitespace-nowrap hidden md:table-cell">
                                        {{ number_format($client->total_spent, 0) }}
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap hidden md:table-cell">
                                        @if($client->total_profit > 0)
                                            <span class="text-green-600 dark:text-green-400 font-medium">
                                                    {{ number_format($client->total_profit, 0) }}
                                                </span>
                                        @else
                                            <span class="text-gray-400">—</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @if($client->pending_balance > 0)
                                            <span class="font-bold text-purple-600 dark:text-purple-400">
                                                    {{ number_format($client->pending_balance, 0) }}
                                                </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 text-green-600 dark:text-green-400 text-xs font-medium">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                    Settled
                                                </span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>

                            {{-- Totals Footer --}}
                            <tfoot class="bg-gray-50 dark:bg-gray-700 border-t-2 border-gray-200 dark:border-gray-600">
                            <tr>
                                <td class="px-4 py-3 font-bold text-gray-900 dark:text-white">Total</td>
                                <td class="px-4 py-3 hidden sm:table-cell"></td>
                                <td class="px-4 py-3 text-right font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                                    {{ number_format($clientTotals->sum('total_received'), 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-orange-600 dark:text-orange-400 whitespace-nowrap hidden md:table-cell">
                                    {{ number_format($clientTotals->sum('total_spent'), 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-green-600 dark:text-green-400 whitespace-nowrap hidden md:table-cell">
                                    {{ number_format($clientTotals->sum('total_profit'), 0) }}
                                </td>
                                <td class="px-4 py-3 text-right font-bold text-purple-600 dark:text-purple-400 whitespace-nowrap">
                                    {{ number_format($clientTotals->sum('pending_balance'), 0) }}
                                </td>
                            </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>

                {{-- Empty state when no clients yet --}}
            @elseif(!$clientFilter && $clientTotals->count() === 0)
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center mb-6">
                    <svg class="w-16 h-16 mx-auto text-gray-400 dark:text-gray-600 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-gray-500 dark:text-gray-400 text-lg font-medium mb-2">No client funds recorded yet</p>
                    <p class="text-gray-400 dark:text-gray-500 text-sm mb-4">Start by recording your first client fund</p>
                    <a href="{{ route('client-funds.create') }}"
                       class="inline-block bg-indigo-600 text-white px-6 py-2 rounded text-sm hover:bg-indigo-700 transition">
                        Record Your First Client Fund
                    </a>
                </div>
            @endif

            {{-- Filtered Client Funds List (only when a client is selected) --}}
            @if($clientFilter)

                {{-- Filter Banner --}}
                <div class="mb-4 flex items-center justify-between bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-700 rounded-lg px-4 py-3">
                    <p class="text-sm text-indigo-700 dark:text-indigo-300">
                        Showing funds for <span class="font-bold">{{ $clientFilter }}</span>
                        <span class="ml-2 text-indigo-500">({{ $clientFunds->total() }} {{ Str::plural('entry', $clientFunds->total()) }})</span>
                    </p>
                    <a href="{{ route('client-funds.index') }}"
                       class="text-xs text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 font-medium flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                        Back to all clients
                    </a>
                </div>

                {{-- Funds List --}}
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow overflow-hidden">
                    <div class="p-3 sm:p-4 border-b border-gray-200 dark:border-gray-700">
                        <h3 class="text-base sm:text-lg font-semibold text-gray-800 dark:text-gray-200">
                            Funds — {{ $clientFilter }}
                        </h3>
                    </div>

                    @forelse($clientFunds as $fund)
                        <div class="p-3 sm:p-4 border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700/50">
                            <div class="flex flex-col gap-3">

                                {{-- Top: Badges & Meta --}}
                                <div class="flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-2">
                                        <span class="px-2 py-1 text-xs rounded-full whitespace-nowrap
                                            {{ $fund->status === 'completed' ? 'bg-green-100 text-green-700' : '' }}
                                            {{ $fund->status === 'pending'   ? 'bg-yellow-100 text-yellow-700' : '' }}
                                            {{ $fund->status === 'partial'   ? 'bg-blue-100 text-blue-700' : '' }}">
                                            {{ ucfirst($fund->status) }}
                                        </span>
                                        <span class="px-2 py-1 text-xs rounded-full whitespace-nowrap
                                            {{ $fund->type === 'commission' ? 'bg-purple-100 text-purple-700' : 'bg-gray-100 text-gray-700' }}">
                                            {{ $fund->type === 'commission' ? '💰 Profit' : '🔄 No Profit' }}
                                        </span>
                                    </div>
                                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200 mb-1">{{ $fund->purpose }}</p>
                                    <p class="text-xs text-gray-500">
                                        {{ $fund->received_date->format('M d, Y') }} · {{ $fund->account->name }}
                                    </p>
                                </div>

                                {{-- Financial Summary --}}
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

                                {{-- Action --}}
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
                            <p class="text-gray-500 text-sm mb-3">
                                No funds found for <span class="font-semibold">{{ $clientFilter }}</span>.
                            </p>
                            <a href="{{ route('client-funds.index') }}"
                               class="inline-block bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 px-4 py-2 rounded text-sm hover:bg-gray-200 transition">
                                Back to All Clients
                            </a>
                        </div>
                    @endforelse

                    {{-- Pagination --}}
                    @if($clientFunds->hasPages())
                        <div class="p-4 border-t border-gray-100 dark:border-gray-700">
                            {{ $clientFunds->links() }}
                        </div>
                    @endif
                </div>

            @endif

        </div>
    </div>

    <x-floating-action-button :quickAccount="$allAccounts->first()" />
</x-app-layout>
