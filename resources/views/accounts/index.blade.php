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

                    {{-- Only show Add Account button if total accounts is less than 7 --}}
                    @if (($accounts->count() + $savingsAccounts->count()) < 7)
                        <a href="{{ route('accounts.create') }}"
                           class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm text-center w-full sm:w-auto">
                            + Add Account
                        </a>
                    @endif
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
                <!-- AFTER -->
                <!-- AFTER -->
                @if($totalSavings > 0)
                    <div class="mt-3 pt-3 border-t border-white/30">
                        <p class="text-xs opacity-90 mb-1">Savings Balance</p>

                        {{-- Locked state --}}
                        <div id="acct-savings-locked">
                            <button onclick="openAcctPinModal()"
                                    class="mt-1 flex items-center gap-2 px-3 py-1.5 bg-white/20 hover:bg-white/30 rounded-lg text-xs font-medium transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Tap to reveal savings
                            </button>
                        </div>

                        {{-- Unlocked state --}}
                        <div id="acct-savings-unlocked" class="hidden">
                            <p class="text-lg font-semibold mt-1">
                                KES {{ number_format($totalSavings, 0, '.', ',') }}
                            </p>
                            <button onclick="acctLockSavings()" class="mt-1 text-xs opacity-70 hover:opacity-100 flex items-center gap-1 transition-opacity">
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                                Lock
                            </button>
                        </div>
                    </div>
                @endif
            </div>

            <!-- Show/Hide Low Balance Accounts Toggle -->
            @if($accounts->count() > 0 || $savingsAccounts->count() > 0)
                <div class="mb-4 flex justify-end">
                    <button onclick="toggleLowBalanceAccounts()"
                            class="text-sm text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 flex items-center gap-2">
                        <span id="toggle-accounts-icon">👁️</span>
                        <span id="toggle-accounts-text">Show accounts with low balance</span>
                    </button>
                </div>
            @endif

            <!-- Regular Accounts Grid -->
            @if($accounts->count() > 0)
                <div class="mb-6">
                    <h3 class="text-base sm:text-lg font-semibold mb-3 text-gray-800 dark:text-gray-200">
                        Main Accounts
                    </h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-5">
                        @foreach($accounts as $account)
                            @php
                                $isLowBalance = $account->current_balance < 1;
                            @endphp
                            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md {{ $isLowBalance ? 'low-balance-account hidden' : '' }} {{ $isLowBalance ? 'opacity-60' : '' }}">
                                <div class="p-4">

                                    <!-- Low Balance Badge -->
                                    @if($isLowBalance)
                                        <div class="mb-2">
                                            <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">
                                                ⚠️ Low Balance
                                            </span>
                                        </div>
                                    @endif

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
                                    <!-- AFTER (restore regular balance display for non-savings accounts) -->
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
                            @php
                                $isLowBalance = $account->current_balance < 1;
                            @endphp
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg shadow-md border-2 border-green-200 dark:border-green-700 {{ $isLowBalance ? 'low-balance-account hidden' : '' }} {{ $isLowBalance ? 'opacity-60' : '' }}">
                                <div class="p-4">

                                    <!-- Low Balance Badge -->
                                    @if($isLowBalance)
                                        <div class="mb-2">
                                            <span class="inline-block bg-yellow-100 text-yellow-800 text-xs px-2 py-1 rounded">
                                                ⚠️ Low Balance
                                            </span>
                                        </div>
                                    @endif

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
                                    <!-- AFTER -->
                                    <div class="mb-3">
                                        <p class="text-xs text-gray-600 dark:text-gray-400 mb-1">Saved Amount</p>

                                        {{-- Locked --}}
                                        <div class="savings-card-locked-{{ $loop->index }}">
                                            <p class="text-sm text-gray-400 dark:text-gray-500 italic flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                                PIN protected
                                            </p>
                                        </div>

                                        {{-- Unlocked --}}
                                        <div class="savings-card-unlocked-{{ $loop->index }} hidden">
                                            <p class="text-xl font-bold text-green-600 dark:text-green-400">
                                                KES {{ number_format($account->current_balance, 0, '.', ',') }}
                                            </p>
                                        </div>
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
        const SAVINGS_PIN_KEY = 'savings_pin_hash';
        const SAVINGS_UNLOCKED_KEY = 'savings_unlocked_until';
        const UNLOCK_DURATION_MS = 5 * 60 * 1000;

        function hashPin(pin) {
            let hash = 0;
            const salted = 'savings_' + pin + '_lock';
            for (let i = 0; i < salted.length; i++) {
                hash = ((hash << 5) - hash) + salted.charCodeAt(i);
                hash |= 0;
            }
            return hash.toString();
        }

        // ===================== PIN UNLOCK MODAL =====================
        let acctPinEntry = '';

        function openAcctPinModal() {
            acctPinEntry = '';
            updateAcctUnlockDots(0);
            document.getElementById('acct-pin-error').classList.add('hidden');
            document.getElementById('acct-pin-unlock-modal').classList.remove('hidden');
        }

        function closeAcctPinModal() {
            acctPinEntry = '';
            updateAcctUnlockDots(0);
            document.getElementById('acct-pin-unlock-modal').classList.add('hidden');
        }

        function acctPinInput(key) {
            if (key === '⌫') {
                acctPinEntry = acctPinEntry.slice(0, -1);
            } else if (acctPinEntry.length < 4) {
                acctPinEntry += key;
            }

            updateAcctUnlockDots(acctPinEntry.length);

            if (acctPinEntry.length === 4) {
                setTimeout(() => {
                    if (hashPin(acctPinEntry) === localStorage.getItem(SAVINGS_PIN_KEY)) {
                        document.getElementById('acct-pin-error').classList.add('hidden');
                        localStorage.setItem(SAVINGS_UNLOCKED_KEY, (Date.now() + UNLOCK_DURATION_MS).toString());
                        closeAcctPinModal();
                        showAcctSavingsUnlocked();
                    } else {
                        document.getElementById('acct-pin-error').classList.remove('hidden');
                        acctPinEntry = '';
                        updateAcctUnlockDots(0);
                    }
                }, 150);
            }
        }

        function updateAcctUnlockDots(count) {
            document.querySelectorAll('.acct-unlock-dot').forEach((dot, i) => {
                dot.classList.toggle('bg-teal-500', i < count);
                dot.classList.toggle('border-teal-500', i < count);
                dot.classList.toggle('border-gray-300', i >= count);
                dot.classList.toggle('dark:border-gray-600', i >= count);
            });
        }

        function showAcctSavingsUnlocked() {
            document.getElementById('acct-savings-locked').classList.add('hidden');
            document.getElementById('acct-savings-unlocked').classList.remove('hidden');

            document.querySelectorAll('[class*="savings-card-locked-"]').forEach(el => el.classList.add('hidden'));
            document.querySelectorAll('[class*="savings-card-unlocked-"]').forEach(el => el.classList.remove('hidden'));

            const remaining = parseInt(localStorage.getItem(SAVINGS_UNLOCKED_KEY)) - Date.now();
            if (remaining > 0) setTimeout(() => acctLockSavings(), remaining);
        }

        function acctLockSavings() {
            localStorage.removeItem(SAVINGS_UNLOCKED_KEY);
            acctPinEntry = '';

            document.getElementById('acct-savings-locked').classList.remove('hidden');
            document.getElementById('acct-savings-unlocked').classList.add('hidden');

            document.querySelectorAll('[class*="savings-card-locked-"]').forEach(el => el.classList.remove('hidden'));
            document.querySelectorAll('[class*="savings-card-unlocked-"]').forEach(el => el.classList.add('hidden'));
        }

        function initAccountsSavingsPin() {
            const savedPin = localStorage.getItem(SAVINGS_PIN_KEY);
            const unlockedUntil = localStorage.getItem(SAVINGS_UNLOCKED_KEY);

            if (!savedPin) {
                document.getElementById('acct-pin-setup-modal').classList.remove('hidden');
                return;
            }

            if (unlockedUntil && Date.now() < parseInt(unlockedUntil)) {
                showAcctSavingsUnlocked();
            }
        }

        // ===================== PIN SETUP =====================
        let acctSetupStage = 'first';
        let acctFirstPin = '';
        let acctSetupEntry = '';

        function acctSetupPinInput(key) {
            if (key === '⌫') {
                acctSetupEntry = acctSetupEntry.slice(0, -1);
            } else if (acctSetupEntry.length < 4) {
                acctSetupEntry += key;
            }

            document.querySelectorAll('.acct-setup-dot').forEach((dot, i) => {
                dot.classList.toggle('bg-indigo-500', i < acctSetupEntry.length);
                dot.classList.toggle('border-indigo-500', i < acctSetupEntry.length);
                dot.classList.toggle('border-gray-300', i >= acctSetupEntry.length);
            });

            if (acctSetupEntry.length === 4) {
                setTimeout(() => {
                    if (acctSetupStage === 'first') {
                        acctFirstPin = acctSetupEntry;
                        acctSetupEntry = '';
                        acctSetupStage = 'confirm';
                        document.getElementById('acct-setup-subtitle').textContent = 'Confirm your 4-digit PIN';
                        document.querySelectorAll('.acct-setup-dot').forEach(d => {
                            d.classList.remove('bg-indigo-500', 'border-indigo-500');
                            d.classList.add('border-gray-300');
                        });
                    } else {
                        if (acctSetupEntry === acctFirstPin) {
                            localStorage.setItem(SAVINGS_PIN_KEY, hashPin(acctSetupEntry));
                            document.getElementById('acct-pin-setup-modal').classList.add('hidden');
                            document.getElementById('acct-setup-error').classList.add('hidden');
                            acctSetupEntry = '';
                            acctSetupStage = 'first';
                            acctFirstPin = '';
                        } else {
                            document.getElementById('acct-setup-error').classList.remove('hidden');
                            acctSetupEntry = '';
                            acctSetupStage = 'first';
                            acctFirstPin = '';
                            document.getElementById('acct-setup-subtitle').textContent = 'Create a 4-digit PIN to protect your savings balance';
                            document.querySelectorAll('.acct-setup-dot').forEach(d => {
                                d.classList.remove('bg-indigo-500', 'border-indigo-500');
                                d.classList.add('border-gray-300');
                            });
                        }
                    }
                }, 150);
            }
        }

        // ===================== REGULAR BALANCE TOGGLE =====================
        let balancesVisible = localStorage.getItem('balancesVisible') === 'true';
        let lowBalanceAccountsVisible = localStorage.getItem('lowBalanceAccountsVisible') === 'true';

        function toggleBalances() {
            balancesVisible = !balancesVisible;
            localStorage.setItem('balancesVisible', balancesVisible);
            updateBalanceVisibility();
        }

        function toggleLowBalanceAccounts() {
            lowBalanceAccountsVisible = !lowBalanceAccountsVisible;
            localStorage.setItem('lowBalanceAccountsVisible', lowBalanceAccountsVisible);
            updateLowBalanceAccountsVisibility();
        }

        function updateBalanceVisibility() {
            document.querySelectorAll('.balance-hidden').forEach(container => {
                const amount = container.querySelector('.balance-amount');
                const placeholder = container.querySelector('.balance-placeholder');
                if (amount && placeholder) {
                    amount.classList.toggle('hidden', !balancesVisible);
                    placeholder.classList.toggle('hidden', balancesVisible);
                }
            });
            document.getElementById('toggle-icon').textContent = balancesVisible ? '🙈' : '👁️';
            document.getElementById('toggle-text').textContent = balancesVisible ? 'Hide' : 'Show';
        }

        function updateLowBalanceAccountsVisibility() {
            document.querySelectorAll('.low-balance-account').forEach(el => el.classList.toggle('hidden', !lowBalanceAccountsVisible));
            document.getElementById('toggle-accounts-icon').textContent = lowBalanceAccountsVisible ? '🙈' : '👁️';
            document.getElementById('toggle-accounts-text').textContent = lowBalanceAccountsVisible
                ? 'Hide accounts with low balance'
                : 'Show accounts with low balance';
        }

        document.addEventListener('DOMContentLoaded', function () {
            updateBalanceVisibility();
            updateLowBalanceAccountsVisibility();
            initAccountsSavingsPin();
        });
    </script>

    <x-floating-action-button :quickAccount="$allAccounts->first()" />
    {{-- PIN Setup Modal --}}
    {{-- PIN Unlock Modal --}}
    <div id="acct-pin-unlock-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 w-80 mx-4">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Savings PIN</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Enter your 4-digit PIN to reveal savings</p>
            </div>

            <div class="flex justify-center gap-3 mb-5">
                @for($i = 0; $i < 4; $i++)
                    <div class="w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-600 acct-unlock-dot"></div>
                @endfor
            </div>

            <div class="grid grid-cols-3 gap-2">
                @foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $key)
                    @if($key === '')
                        <div></div>
                    @else
                        <button onclick="acctPinInput('{{ $key }}')"
                                class="py-3 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95 text-gray-800 dark:text-white font-semibold text-base transition-all duration-150">
                            {{ $key }}
                        </button>
                    @endif
                @endforeach
            </div>

            <p id="acct-pin-error" class="text-xs text-red-500 text-center mt-3 hidden">Incorrect PIN. Try again.</p>
            <button onclick="closeAcctPinModal()"
                    class="mt-4 w-full text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                Cancel
            </button>
        </div>
    </div>

    {{-- PIN Setup Modal --}}
    <div id="acct-pin-setup-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center hidden">
        <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-2xl p-6 w-80 mx-4">
            <div class="text-center mb-5">
                <div class="w-14 h-14 bg-teal-100 dark:bg-teal-900 rounded-full flex items-center justify-center mx-auto mb-3">
                    <svg class="w-7 h-7 text-teal-600 dark:text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                </div>
                <h3 class="text-lg font-bold text-gray-900 dark:text-white">Set Savings PIN</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1" id="acct-setup-subtitle">Create a 4-digit PIN to protect your savings balance</p>
            </div>
            <div class="flex justify-center gap-3 mb-5">
                @for($i = 0; $i < 4; $i++)
                    <div class="w-4 h-4 rounded-full border-2 border-gray-300 dark:border-gray-600 acct-setup-dot"></div>
                @endfor
            </div>
            <div class="grid grid-cols-3 gap-2">
                @foreach([1,2,3,4,5,6,7,8,9,'',0,'⌫'] as $key)
                    @if($key === '')
                        <div></div>
                    @else
                        <button onclick="acctSetupPinInput('{{ $key }}')"
                                class="py-3 rounded-xl bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 active:scale-95 text-gray-800 dark:text-white font-semibold text-base transition-all duration-150">
                            {{ $key }}
                        </button>
                    @endif
                @endforeach
            </div>
            <p id="acct-setup-error" class="text-xs text-red-500 text-center mt-3 hidden">PINs do not match. Try again.</p>
            <button onclick="document.getElementById('acct-pin-setup-modal').classList.add('hidden')"
                    class="mt-4 w-full text-sm text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
                Cancel
            </button>
        </div>
    </div>
</x-app-layout>
