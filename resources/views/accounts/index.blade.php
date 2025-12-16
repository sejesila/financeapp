<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Accounts') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Page Subtitle & Actions -->
            <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-6 gap-4">
                <p class="text-gray-600 dark:text-gray-400 text-sm">
                    Manage your cash, bank, and mobile money accounts
                </p>
                <div class="flex space-x-3">
                    <a href="{{ route('accounts.transfer') }}"
                       class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded">
                        ↔ Transfer Money
                    </a>
                    <a href="{{ route('accounts.create') }}"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">
                        + Add Account
                    </a>
                </div>
            </div>

            <!-- Flash Messages -->
            @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="bg-{{ $color }}-100 text-{{ $color }}-700 p-4 rounded mb-6">
                        {{ session($key) }}
                    </div>
                @endif
            @endforeach

            <!-- Total Balance Card -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-6 rounded-lg shadow-lg mb-6">
                <p class="text-sm font-semibold mb-2">Total Cash</p>
                <p class="text-4xl font-bold">
                    KES {{ number_format($totalBalance, 0, '.', ',') }}
                </p>
                <p class="text-xs mt-2 opacity-90">
                    Across {{ $accounts->count() }} active accounts
                </p>
            </div>

            <!-- Accounts Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                @forelse($accounts as $account)
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md hover:shadow-lg transition">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex items-center space-x-3 mb-4">
                                @php
                                    switch($account->type) {
                                        case 'cash':
                                            $bgColor = 'bg-green-100';
                                            $icon = '💵';
                                            break;
                                        case 'mpesa':
                                            $bgColor = 'bg-green-100';
                                            $icon = '<span class="text-green-600 font-bold">M</span>';
                                            break;
                                        case 'airtel_money':
                                            $bgColor = 'bg-red-100';
                                            $icon = '<span class="text-red-600 font-bold">A</span>';
                                            break;
                                        case 'bank':
                                            $bgColor = 'bg-purple-100';
                                            $icon = '🏦';
                                            break;
                                        default:
                                            $bgColor = 'bg-gray-100';
                                            $icon = '?';
                                    }
                                @endphp

                                <div class="w-12 h-12 rounded-full flex items-center justify-center {{ $bgColor }}">
                                    {!! $icon !!}
                                </div>

                                <div>
                                    <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ $account->name }}</h3>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 capitalize">
                                        {{ str_replace('_', ' ', $account->type) }}
                                    </p>
                                </div>
                            </div>

                            <!-- Balance -->
                            <div class="mb-4">
                                <p class="text-xs text-gray-600 dark:text-gray-400">Available Balance</p>
                                <p class="text-2xl font-bold {{ $account->current_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    KES {{ number_format($account->current_balance, 0, '.', ',') }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    Opening: KES {{ number_format($account->initial_balance, 0, '.', ',') }}
                                </p>
                            </div>

                            @if($account->notes)
                                <p class="text-xs text-gray-600 dark:text-gray-400 mb-4">{{ $account->notes }}</p>
                            @endif

                            <!-- Actions -->
                            <div class="flex space-x-2">
                                <a href="{{ route('accounts.show', $account) }}"
                                   class="flex-1 bg-blue-500 text-white text-center py-2 rounded hover:bg-blue-600 text-sm">
                                    View
                                </a>

                                <a href="{{ route('accounts.topup', $account) }}"
                                   class="flex-1 bg-green-500 text-white text-center py-2 rounded hover:bg-green-600 text-sm">
                                    Top Up
                                </a>

                                <form method="POST"
                                      action="{{ route('accounts.destroy', $account) }}"
                                      onsubmit="return confirm('Are you sure? This action cannot be undone.');"
                                      class="flex-1">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit"
                                            class="w-full bg-red-500 text-white py-2 rounded hover:bg-red-600 text-sm">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="col-span-3 text-center py-12 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <p class="text-gray-500 dark:text-gray-400 text-sm mb-4">
                            No accounts yet. Create your first account to get started!
                        </p>
                        <a href="{{ route('accounts.create') }}"
                           class="bg-indigo-600 text-white px-6 py-2 rounded hover:bg-indigo-700">
                            + Add First Account
                        </a>
                    </div>
                @endforelse
            </div>

            <!-- Recent Transfers -->
            @if($recentTransfers->count() > 0)
                <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-md">
                    <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200 mb-4">Recent Transfers</h3>
                    <div class="space-y-3">
                        @foreach($recentTransfers as $transfer)
                            <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="flex items-center space-x-3">
                                    <span class="text-lg">↔</span>
                                    <div>
                                        <p class="font-semibold text-sm text-gray-800 dark:text-gray-200">
                                            {{ $transfer->fromAccount->name }} → {{ $transfer->toAccount->name }}
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::parse($transfer->date)->format('M d, Y') }}
                                        </p>
                                        @if($transfer->description)
                                            <p class="text-xs text-gray-600 dark:text-gray-400">
                                                {{ $transfer->description }}
                                            </p>
                                        @endif
                                    </div>
                                </div>
                                <p class="font-semibold text-purple-600 text-sm">
                                    KES {{ number_format($transfer->amount, 0, '.', ',') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
