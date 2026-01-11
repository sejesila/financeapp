<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Accounts') }}
        </h2>
    </x-slot>

    <div class="py-3 sm:py-8 lg:py-12">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">

            <!-- Page Subtitle & Actions -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-3">
                <p class="text-gray-600 dark:text-gray-400 text-xs sm:text-sm">
                    Manage your cash, bank, mobile money, and savings accounts
                </p>

                <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
                    @if (($accounts->count() + $savingsAccounts->count()) >= 2)
                        <a href="{{ route('accounts.transfer') }}"
                           class="bg-purple-600 hover:bg-purple-700 text-white px-4 py-2 rounded text-sm text-center w-full sm:w-auto">
                            ↔ Transfer Money
                        </a>
                    @endif
                    <a href="{{ route('accounts.create') }}"
                       class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm text-center w-full sm:w-auto">
                        + Add Account
                    </a>
                </div>
            </div>

            <!-- Flash Messages -->
            @foreach (['success' => 'green', 'error' => 'red', 'info' => 'blue'] as $key => $color)
                @if(session($key))
                    <div class="bg-{{ $color }}-100 text-{{ $color }}-700 p-3 rounded mb-4 text-sm">
                        {{ session($key) }}
                    </div>
                @endif
            @endforeach

            <!-- Total Balance Card -->
            <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white p-4 sm:p-6 rounded-lg shadow-lg mb-4">
                <div class="flex items-center justify-between mb-2">
                    <p class="text-xs sm:text-sm font-semibold">Total Cash</p>
                    <button onclick="toggleBalances()" class="text-white/80 hover:text-white text-xs flex items-center gap-1">
                        <span id="toggle-icon">👁️</span>
                        <span id="toggle-text">Show</span>
                    </button>
                </div>
                <p class="text-2xl sm:text-3xl lg:text-4xl font-bold balance-hidden">
                    <span class="balance-amount hidden">KES {{ number_format($totalBalance, 0, '.', ',') }}</span>
                    <span class="balance-placeholder">KES ••••••</span>
                </p>
                <p class="text-xs mt-1 opacity-90">
                    Across {{ $accounts->count() + $savingsAccounts->count() }} {{ Str::plural('account', $accounts->count() + $savingsAccounts->count()) }}
                </p>
                @if($totalSavings > 0)
                    <div class="mt-3 pt-3 border-t border-white/30 balance-hidden">
                        <p class="text-xs opacity-90">Savings Balance</p>
                        <p class="text-lg font-semibold">
                            <span class="balance-amount hidden">KES {{ number_format($totalSavings, 0, '.', ',') }}</span>
                            <span class="balance-placeholder">KES ••••••</span>
                        </p>
                    </div>
                @endif
            </div>

            <!-- Regular Accounts Grid -->
            @if($accounts->count() > 0)
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">
                        Main Accounts
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5">
                        @foreach($accounts as $account)
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md">
                                <div class="p-4">

                                    <!-- Header -->
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-gray-100">
                                            @if(strtolower($account->type) === 'bank')
                                                <img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-8 h-8 object-contain">
                                            @elseif(strtolower($account->type) === 'mpesa')
                                                <img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-8 h-8 object-contain">
                                            @elseif(strtolower($account->type) === 'airtel_money')
                                                <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-8 h-8 object-contain">
                                            @else
                                                <span class="font-bold text-gray-700">{{ substr($account->name, 0, 1) }}</span>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-sm text-gray-800 dark:text-gray-200 truncate">
                                                {{ $account->name }}
                                            </h3>
                                        </div>
                                    </div>

                                    <!-- Balance -->
                                    <div class="mb-3 balance-hidden">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Available Balance</p>
                                        <p class="text-xl font-bold {{ $account->current_balance >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            <span class="balance-amount hidden">KES {{ number_format($account->current_balance, 0, '.', ',') }}</span>
                                            <span class="balance-placeholder">KES ••••••</span>
                                        </p>
                                    </div>

                                    @if($account->notes)
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                            {{ $account->notes }}
                                        </p>
                                    @endif

                                    <!-- Actions -->
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a href="{{ route('accounts.show', $account) }}"
                                           class="flex-1 bg-blue-500 text-white text-center py-2 rounded hover:bg-blue-600 text-xs sm:text-sm">
                                            View
                                        </a>

                                        <a href="{{ route('accounts.topup', $account) }}"
                                           class="flex-1 bg-green-500 text-white text-center py-2 rounded hover:bg-green-600 text-xs sm:text-sm">
                                            Top Up
                                        </a>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Savings Accounts Grid -->
            @if($savingsAccounts->count() > 0)
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200 flex items-center gap-2">
                        <span>💰</span> Savings Accounts
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5">
                        @foreach($savingsAccounts as $account)
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg shadow-md border-2 border-green-200 dark:border-green-700">
                                <div class="p-4">

                                    <!-- Header -->
                                    <div class="flex items-center gap-3 mb-3">
                                        <div class="w-10 h-10 rounded-full flex items-center justify-center bg-green-100 dark:bg-green-800">
                                            @if(str_contains(strtolower($account->name), 'mshwari'))
                                                <img src="{{ asset('images/mpesa.png') }}" alt="M-Shwari" class="w-8 h-8 object-contain">
                                            @elseif(str_contains(strtolower($account->name), 'kcb'))
                                                <span class="text-green-700 dark:text-green-300 font-bold text-xs">KCB</span>
                                            @elseif(str_contains(strtolower($account->name), 'sanlam'))
                                                <span class="text-green-700 dark:text-green-300 font-bold text-xs">SAN</span>
                                            @else
                                                <span class="text-green-700 dark:text-green-300 font-bold">💵</span>
                                            @endif
                                        </div>

                                        <div class="min-w-0">
                                            <h3 class="font-semibold text-sm text-gray-800 dark:text-gray-200 truncate">
                                                {{ $account->name }}
                                            </h3>
                                            <p class="text-xs text-green-600 dark:text-green-400">
                                                Savings Account
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Balance -->
                                    <div class="mb-3 balance-hidden">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Saved Amount</p>
                                        <p class="text-xl font-bold text-green-600 dark:text-green-400">
                                            <span class="balance-amount hidden">KES {{ number_format($account->current_balance, 0, '.', ',') }}</span>
                                            <span class="balance-placeholder">KES ••••••</span>
                                        </p>
                                    </div>

                                    @if($account->notes)
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-3 line-clamp-2">
                                            {{ $account->notes }}
                                        </p>
                                    @endif

                                    <!-- Actions -->
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <a href="{{ route('accounts.show', $account) }}"
                                           class="flex-1 bg-green-500 text-white text-center py-2 rounded hover:bg-green-600 text-xs sm:text-sm">
                                            View
                                        </a>

                                        <a href="{{ route('accounts.topup', $account) }}"
                                           class="flex-1 bg-emerald-500 text-white text-center py-2 rounded hover:bg-emerald-600 text-xs sm:text-sm">
                                            Deposit
                                        </a>
                                    </div>

                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Empty State -->
            @if($accounts->count() === 0 && $savingsAccounts->count() === 0)
                <div class="col-span-full text-center py-10 bg-gray-50 dark:bg-gray-700 rounded-lg">
                    <p class="text-gray-500 text-sm mb-4 px-4">
                        No accounts yet. Create your first account to get started!
                    </p>
                    <a href="{{ route('accounts.create') }}"
                       class="inline-block bg-indigo-600 text-white px-6 py-2 rounded text-sm">
                        + Add First Account
                    </a>
                </div>
            @endif

            <!-- Recent Transfers -->
            @if($recentTransfers->count())
                <div class="bg-white dark:bg-gray-800 p-4 sm:p-6 rounded-lg shadow-md">
                    <h3 class="text-base sm:text-lg font-semibold mb-4">
                        Recent Transfers
                    </h3>

                    <div class="space-y-3">
                        @foreach($recentTransfers as $transfer)
                            <div class="flex flex-col sm:flex-row sm:justify-between gap-2 p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                <div class="min-w-0">
                                    <p class="font-semibold text-xs sm:text-sm break-words">
                                        {{ $transfer->fromAccount->name }} → {{ $transfer->toAccount->name }}
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        {{ \Carbon\Carbon::parse($transfer->date)->format('M d, Y') }}
                                    </p>
                                </div>
                                <p class="font-semibold text-purple-600 text-sm whitespace-nowrap">
                                    KES {{ number_format($transfer->amount, 0, '.', ',') }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>
    </div>

    <script>
        // Load visibility state from localStorage
        let balancesVisible = localStorage.getItem('balancesVisible') === 'true';

        function toggleBalances() {
            balancesVisible = !balancesVisible;
            localStorage.setItem('balancesVisible', balancesVisible);
            updateBalanceVisibility();
        }

        function updateBalanceVisibility() {
            const balanceContainers = document.querySelectorAll('.balance-hidden');
            const toggleIcon = document.getElementById('toggle-icon');
            const toggleText = document.getElementById('toggle-text');

            balanceContainers.forEach(container => {
                const amount = container.querySelector('.balance-amount');
                const placeholder = container.querySelector('.balance-placeholder');

                if (balancesVisible) {
                    amount.classList.remove('hidden');
                    placeholder.classList.add('hidden');
                } else {
                    amount.classList.add('hidden');
                    placeholder.classList.remove('hidden');
                }
            });

            if (balancesVisible) {
                toggleIcon.textContent = '🙈';
                toggleText.textContent = 'Hide';
            } else {
                toggleIcon.textContent = '👁️';
                toggleText.textContent = 'Show';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', updateBalanceVisibility);
    </script>

    <x-floating-action-button :quickAccount="$allAccounts->first()" />
</x-app-layout>
