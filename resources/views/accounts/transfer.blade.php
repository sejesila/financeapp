<x-app-layout>

    <x-slot name="header">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Transfer Money') }}
            </h2>
            <a href="{{ route('accounts.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Accounts
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div x-data="transferForm()">
            <form method="POST" action="{{ route('accounts.transferPost') }}" class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                @csrf

                <!-- From Account -->
                <div class="mb-4">
                    <label for="from_account_id" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">From Account</label>
                    <select
                        name="from_account_id"
                        id="from_account_id"
                        x-model="fromAccountId"
                        @change="calculateFee()"
                        required
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select source account</option>
                        @foreach($sourceAccounts as $account)
                            <option
                                value="{{ $account->id }}"
                                data-type="{{ $account->type }}"
                                {{ old('from_account_id') == $account->id ? 'selected' : '' }}
                            >
                                @if($account->type == 'cash') 💵
                                @elseif($account->type == 'mpesa') 📱
                                @elseif($account->type == 'airtel_money') 📲
                                @elseif($account->type == 'bank') 🏦
                                @elseif($account->type == 'savings') 💰
                                @endif
                                {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                            </option>
                        @endforeach
                    </select>
                    @error('from_account_id')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- To Account -->
                <div class="mb-4">
                    <label for="to_account_id" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">To Account</label>
                    <select
                        name="to_account_id"
                        id="to_account_id"
                        x-model="toAccountId"
                        @change="calculateFee()"
                        required
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select destination account</option>
                        @foreach($destinationAccounts as $account)
                            <option
                                value="{{ $account->id }}"
                                data-type="{{ $account->type }}"
                                {{ old('to_account_id') == $account->id ? 'selected' : '' }}
                            >
                                @if($account->type == 'cash') 💵
                                @elseif($account->type == 'mpesa') 📱
                                @elseif($account->type == 'airtel_money') 📲
                                @elseif($account->type == 'bank') 🏦
                                @elseif($account->type == 'savings') 💰
                                @endif
                                {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                            </option>
                        @endforeach
                    </select>
                    @error('to_account_id')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Amount -->
                <div class="mb-4">
                    <label for="amount" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        id="amount"
                        x-model="amount"
                        @input="calculateFee()"
                        value="{{ old('amount') }}"
                        placeholder="Enter amount to transfer"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                    @error('amount')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror

                    <!-- Minimum withdrawal warning -->
                    <p x-show="isWithdrawal && amount > 0 && amount < 50" class="text-red-500 text-sm mt-2">
                        ⚠️ Minimum M-Pesa withdrawal amount is KES 50
                    </p>
                </div>

                <!-- Withdrawal Fee Display -->
                <div
                    x-show="showWithdrawalFee && withdrawalFee > 0"
                    x-transition
                    class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-3 mb-4"
                >
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    <span x-text="fromAccountType === 'mpesa' ? 'M-Pesa' : 'Airtel Money'"></span> Withdrawal Fee
                                </p>
                                <span class="text-lg font-bold text-yellow-800 dark:text-yellow-200" x-text="'KES ' + withdrawalFee.toLocaleString()"></span>
                            </div>
                            <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                Agent withdrawal fee will be deducted from <span x-text="fromAccountName"></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Deduction Display -->
                <div
                    x-show="showWithdrawalFee && withdrawalFee > 0"
                    x-transition
                    class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded p-3 mb-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                            Total Deduction from <span x-text="fromAccountName"></span>:
                        </span>
                        <span class="text-lg font-bold text-indigo-800 dark:text-indigo-200" x-text="'KES ' + totalDeduction.toLocaleString()"></span>
                    </div>
                    <p class="text-xs text-indigo-600 dark:text-indigo-300 mt-1">
                        Transfer: KES <span x-text="parseFloat(amount || 0).toLocaleString()"></span> + Fee: KES <span x-text="withdrawalFee.toLocaleString()"></span>
                    </p>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Date</label>
                    <input
                        type="date"
                        name="date"
                        id="date"
                        value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                    @error('date')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <!-- Description -->
                <div class="mb-6">
                    <label for="description" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Description (Optional)</label>
                    <input
                        type="text"
                        name="description"
                        id="description"
                        value="{{ old('description') }}"
                        placeholder="e.g., Withdraw cash from M-Pesa"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    >
                    @error('description')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex items-center justify-between">
                    <a href="{{ route('accounts.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</a>
                    <button type="submit" class="bg-purple-600 text-white px-6 py-2 rounded hover:bg-purple-700 focus:outline-none">
                        Transfer Money
                    </button>
                </div>
            </form>
        </div>

        <!-- Quick Info -->
        <div class="mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <strong>💡 Tip:</strong> Transfers don't affect your budget categories. They simply move money between
                your accounts. Withdrawal fees (M-Pesa/Airtel to Cash/Bank) are automatically calculated and recorded.
            </p>
        </div>
    </div>

    <script>
        function transferForm() {
            return {
                fromAccountId: '{{ old('from_account_id') }}',
                toAccountId: '{{ old('to_account_id') }}',
                amount: {{ old('amount', 0) }},
                withdrawalFee: 0,
                showWithdrawalFee: false,
                fromAccountType: '',
                fromAccountName: '',

                get totalDeduction() {
                    return parseFloat(this.amount || 0) + this.withdrawalFee;
                },

                get isWithdrawal() {
                    const fromAccount = this.getAccount(this.fromAccountId);
                    const toAccount = this.getAccount(this.toAccountId);

                    if (!fromAccount || !toAccount) return false;

                    const mobileMoneyTypes = ['mpesa', 'airtel_money'];
                    const cashTypes = ['cash', 'bank'];

                    return mobileMoneyTypes.includes(fromAccount.type) &&
                        cashTypes.includes(toAccount.type);
                },

                getAccount(id) {
                    if (!id) return null;
                    const select = document.querySelector(`option[value="${id}"]`);
                    if (!select) return null;

                    return {
                        type: select.dataset.type,
                        name: select.textContent.split('(')[0].trim()
                    };
                },

                calculateFee() {
                    this.showWithdrawalFee = this.isWithdrawal;

                    const fromAccount = this.getAccount(this.fromAccountId);
                    if (fromAccount) {
                        this.fromAccountType = fromAccount.type;
                        this.fromAccountName = fromAccount.name;
                    }

                    if (!this.isWithdrawal || this.amount < 50) {
                        this.withdrawalFee = 0;
                        return;
                    }

                    const tiers = [
                        { min: 50, max: 100, cost: 11 },
                        { min: 101, max: 500, cost: 29 },
                        { min: 501, max: 1000, cost: 29 },
                        { min: 1001, max: 1500, cost: 29 },
                        { min: 1501, max: 2500, cost: 29 },
                        { min: 2501, max: 3500, cost: 52 },
                        { min: 3501, max: 5000, cost: 69 },
                        { min: 5001, max: 7500, cost: 87 },
                        { min: 7501, max: 10000, cost: 115 },
                        { min: 10001, max: 15000, cost: 167 },
                        { min: 15001, max: 20000, cost: 185 },
                        { min: 20001, max: 35000, cost: 197 },
                        { min: 35001, max: 50000, cost: 278 },
                        { min: 50001, max: 250000, cost: 309 },
                    ];

                    for (let tier of tiers) {
                        if (this.amount >= tier.min && this.amount <= tier.max) {
                            this.withdrawalFee = tier.cost;
                            return;
                        }
                    }

                    this.withdrawalFee = 309; // Max fee
                },

                init() {
                    // Calculate on init if old values exist
                    this.$nextTick(() => {
                        this.calculateFee();
                        this.updateDestinationOptions();
                    });
                }
            }
        }

        document.addEventListener('DOMContentLoaded', () => {
            const fromSelect = document.getElementById('from_account_id');
            const toSelect = document.getElementById('to_account_id');

            if (!fromSelect || !toSelect) return;

            window.updateDestinationOptions = function() {
                const fromValue = fromSelect.value;

                Array.from(toSelect.options).forEach(option => {
                    if (!option.value) return; // skip placeholder

                    option.disabled = option.value === fromValue;
                    option.hidden = option.value === fromValue;
                });

                // Reset destination if it became invalid
                if (toSelect.value === fromValue) {
                    toSelect.value = '';
                }
            }

            fromSelect.addEventListener('change', window.updateDestinationOptions);

            // Run once on load (for old() values)
            window.updateDestinationOptions();
        });
    </script>

</x-app-layout>
