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
                        @change="calculateFee(); updateDestinationOptions();"
                        required
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="">Select source account</option>
                        @foreach($sourceAccounts as $account)
                            <option
                                value="{{ $account->id }}"
                                data-type="{{ $account->type }}"
                                data-name="{{ $account->name }}"
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
                                data-name="{{ $account->name }}"
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

                    <!-- Bank transfer restriction notice -->
                    <p x-show="fromAccountType === 'bank'" class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                        💡 Bank transfers are only available to M-Pesa and Airtel Money accounts
                    </p>

                    <!-- Mshwari transfer restriction notice -->
                    <p x-show="fromAccountType === 'savings'" class="text-xs text-blue-600 dark:text-blue-400 mt-2">
                        💡 Mshwari transfers are only available to M-Pesa accounts
                    </p>
                </div>

                <!-- Amount -->
                <div class="mb-4">
                    <label for="amount" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        id="amount"
                        x-model.number="amount"
                        @input="calculateFee()"
                        placeholder="Enter amount to transfer"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                    @error('amount')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror

                    <!-- Minimum withdrawal warning (only for cash withdrawals) -->
                    <p x-show="feeType === 'withdrawal' && amount > 0 && amount < 50" class="text-red-500 text-sm mt-2">
                        ⚠️ Minimum M-Pesa withdrawal amount is KES 50
                    </p>
                </div>

                <!-- Transaction Fee Display -->
                <div
                    x-show="showFee && transactionFee > 0"
                    x-transition
                    :class="feeType === 'withdrawal' ? 'bg-yellow-50 dark:bg-yellow-900/20 border-yellow-200 dark:border-yellow-800' : 'bg-blue-50 dark:bg-blue-900/20 border-blue-200 dark:border-blue-800'"
                    class="border rounded p-3 mb-4"
                >
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" :class="feeType === 'withdrawal' ? 'text-yellow-600 dark:text-yellow-400' : 'text-blue-600 dark:text-blue-400'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="flex-1">
                            <div class="flex items-center justify-between">
                                <p class="text-sm font-medium" :class="feeType === 'withdrawal' ? 'text-yellow-800 dark:text-yellow-200' : 'text-blue-800 dark:text-blue-200'">
                                    <span x-text="fromAccountType === 'mpesa' ? 'M-Pesa' : 'Airtel Money'"></span>
                                    <span x-text="feeType === 'withdrawal' ? ' Withdrawal Fee' : ' PayBill Fee'"></span>
                                </p>
                                <span class="text-lg font-bold" :class="feeType === 'withdrawal' ? 'text-yellow-800 dark:text-yellow-200' : 'text-blue-800 dark:text-blue-200'" x-text="'KES ' + transactionFee.toLocaleString()"></span>
                            </div>
                            <p class="text-xs mt-1" :class="feeType === 'withdrawal' ? 'text-yellow-700 dark:text-yellow-300' : 'text-blue-700 dark:text-blue-300'">
                                <span x-show="feeType === 'withdrawal'">Agent withdrawal fee will be deducted from <span x-text="fromAccountName"></span></span>
                                <span x-show="feeType === 'paybill'">PayBill transaction fee will be deducted from <span x-text="fromAccountName"></span></span>
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Total Deduction Display -->
                <div
                    x-show="showFee && transactionFee > 0"
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
                        Transfer: KES <span x-text="parseFloat(amount || 0).toLocaleString()"></span> + Fee: KES <span x-text="transactionFee.toLocaleString()"></span>
                    </p>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Date</label>
                    <input
                        type="date"
                        name="date"
                        id="date"
                        x-model="date"
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
                        x-model="description"
                        placeholder="e.g., Transfer to savings"
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

    </div>

    <script>
        function transferForm() {
            return {
                fromAccountId: '{{ old('from_account_id') }}',
                toAccountId: '{{ old('to_account_id') }}',
                amount: {{ old('amount') ?: 'null' }},
                date: '{{ old('date', date('Y-m-d')) }}',
                description: '{{ old('description') }}',
                transactionFee: 0,
                showFee: false,
                feeType: null, // 'withdrawal' or 'paybill'
                fromAccountType: '',
                fromAccountName: '',
                toAccountType: '',

                get totalDeduction() {
                    return parseFloat(this.amount || 0) + this.transactionFee;
                },

                getAccount(id) {
                    if (!id) return null;
                    const select = document.querySelector(`option[value="${id}"]`);
                    if (!select) return null;

                    return {
                        type: select.dataset.type,
                        name: select.dataset.name || select.textContent.split('(')[0].trim()
                    };
                },

                calculateFee() {
                    const fromAccount = this.getAccount(this.fromAccountId);
                    const toAccount = this.getAccount(this.toAccountId);

                    if (!fromAccount || !toAccount || !this.amount || this.amount <= 0) {
                        this.showFee = false;
                        this.transactionFee = 0;
                        this.feeType = null;
                        return;
                    }

                    this.fromAccountType = fromAccount.type;
                    this.fromAccountName = fromAccount.name;
                    this.toAccountType = toAccount.type;

                    const mobileMoneyTypes = ['mpesa', 'airtel_money'];
                    const isMobileMoney = mobileMoneyTypes.includes(fromAccount.type);

                    // M-Pesa/Airtel to Cash = Withdrawal fee
                    if (isMobileMoney && toAccount.type === 'cash') {
                        this.feeType = 'withdrawal';
                        this.transactionFee = this.getWithdrawalFee(parseFloat(this.amount), fromAccount.type);
                        this.showFee = this.transactionFee > 0;
                    }
                    // M-Pesa/Airtel to Bank = PayBill fee
                    else if (isMobileMoney && toAccount.type === 'bank') {
                        this.feeType = 'paybill';
                        this.transactionFee = this.getPayBillFee(parseFloat(this.amount), fromAccount.type);
                        this.showFee = this.transactionFee > 0;
                    }
                    // All other transfers = No fee
                    else {
                        this.feeType = null;
                        this.transactionFee = 0;
                        this.showFee = false;
                    }
                },

                getWithdrawalFee(amount, accountType) {
                    // Return 0 if amount is invalid or below minimum
                    if (!amount || amount < 50) {
                        return 0;
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
                        if (amount >= tier.min && amount <= tier.max) {
                            return tier.cost;
                        }
                    }

                    // If amount exceeds all tiers, return the highest tier cost
                    return amount > 250000 ? 309 : 0;
                },

                getPayBillFee(amount, accountType) {
                    // Return 0 if amount is invalid or zero
                    if (!amount || amount <= 0) {
                        return 0;
                    }

                    if (accountType === 'airtel_money') {
                        return 0; // Airtel Money PayBill is free
                    }

                    // M-Pesa PayBill fees
                    const tiers = [
                        { min: 1, max: 49, cost: 0 },
                        { min: 50, max: 100, cost: 0 },
                        { min: 101, max: 500, cost: 5 },
                        { min: 501, max: 1000, cost: 10 },
                        { min: 1001, max: 1500, cost: 15 },
                        { min: 1501, max: 2500, cost: 20 },
                        { min: 2501, max: 3500, cost: 25 },
                        { min: 3501, max: 5000, cost: 34 },
                        { min: 5001, max: 7500, cost: 42 },
                        { min: 7501, max: 10000, cost: 48 },
                        { min: 10001, max: 15000, cost: 57 },
                        { min: 15001, max: 20000, cost: 62 },
                        { min: 20001, max: 25000, cost: 67 },
                        { min: 25001, max: 30000, cost: 72 },
                        { min: 30001, max: 35000, cost: 83 },
                        { min: 35001, max: 40000, cost: 99 },
                        { min: 40001, max: 45000, cost: 103 },
                        { min: 45001, max: 50000, cost: 108 },
                        { min: 50001, max: 250000, cost: 108 },
                    ];

                    for (let tier of tiers) {
                        if (amount >= tier.min && amount <= tier.max) {
                            return tier.cost;
                        }
                    }

                    // If amount exceeds all tiers, return the highest tier cost
                    return amount > 250000 ? 108 : 0;
                },

                updateDestinationOptions() {
                    const fromAccount = this.getAccount(this.fromAccountId);
                    const toSelect = document.getElementById('to_account_id');
                    if (!toSelect) return;

                    Array.from(toSelect.options).forEach(option => {
                        if (!option.value) return;

                        const optionType = option.dataset.type;

                        // Disable same account
                        if (option.value === this.fromAccountId) {
                            option.disabled = true;
                            option.hidden = true;
                            return;
                        }

                        // If source is bank, only allow mpesa and airtel_money
                        if (fromAccount && fromAccount.type === 'bank') {
                            if (optionType === 'mpesa' || optionType === 'airtel_money') {
                                option.disabled = false;
                                option.hidden = false;
                            } else {
                                option.disabled = true;
                                option.hidden = true;
                            }
                        }
                        // If source is savings (Mshwari), only allow mpesa
                        else if (fromAccount && fromAccount.type === 'savings') {
                            if (optionType === 'mpesa') {
                                option.disabled = false;
                                option.hidden = false;
                            } else {
                                option.disabled = true;
                                option.hidden = true;
                            }
                        }
                        else {
                            // For non-bank/non-savings sources, all accounts except self are available
                            option.disabled = false;
                            option.hidden = false;
                        }
                    });

                    // Auto-select M-Pesa if source is bank/savings and no destination is selected
                    if (fromAccount && (fromAccount.type === 'bank' || fromAccount.type === 'savings') && !this.toAccountId) {
                        const mpesaOption = Array.from(toSelect.options).find(opt =>
                            opt.dataset.type === 'mpesa' && opt.value !== this.fromAccountId
                        );
                        if (mpesaOption) {
                            this.toAccountId = mpesaOption.value;
                        }
                    }

                    // Clear selection if currently selected account is now disabled
                    const currentOption = toSelect.querySelector(`option[value="${this.toAccountId}"]`);
                    if (currentOption && (currentOption.disabled || currentOption.hidden)) {
                        this.toAccountId = '';
                    }
                },

                init() {
                    this.$nextTick(() => {
                        this.updateDestinationOptions();
                        this.calculateFee();
                    });
                }
            }
        }
    </script>
</x-app-layout>
