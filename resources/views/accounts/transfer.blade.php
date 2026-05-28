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

                {{-- Hidden input so the manual fee is submitted with the form --}}
                <input type="hidden" name="transaction_fee" :value="transactionFee">

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
                                {{ $account->name }}
                                @if($account->type !== 'savings')
                                    ({{ number_format($account->current_balance, 0, '.', ',') }})
                                @endif
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
                                {{ $account->name }}
                                @if($account->type !== 'savings')
                                    ({{ number_format($account->current_balance, 0, '.', ',') }})
                                @endif
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
                        x-model.number="amount"
                        @input="calculateFee()"
                        placeholder="Enter amount to transfer"
                        class="w-full border border-gray-300 dark:border-gray-600 rounded px-4 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                    @error('amount')
                    <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                    @enderror

                    <!-- Minimum withdrawal warning -->
                    <p x-show="feeType === 'withdrawal' && amount > 0 && amount < 50" class="text-red-500 text-sm mt-2">
                        ⚠️ Minimum M-Pesa withdrawal amount is KES 50
                    </p>
                </div>

                <!-- Editable Transaction Fee -->
                <div x-show="showFee" x-transition class="mb-4">
                    <label class="block font-semibold mb-2"
                           :class="{
                            'text-orange-700 dark:text-orange-300': feeType === 'atm',
                            'text-yellow-700 dark:text-yellow-300': feeType === 'withdrawal',
                            'text-blue-700 dark:text-blue-300': feeType === 'paybill',
                            'text-purple-700 dark:text-purple-300': feeType === 'savings'
                        }">
                        <span x-text="
    feeType === 'atm'      ? '🏧 ATM Withdrawal Fee' :
    feeType === 'savings'  ? '💰 Savings Withdrawal Fee' :
    feeType === 'withdrawal' ? '📱 ' + (fromAccountType === 'mpesa' ? 'M-Pesa' : 'Airtel Money') + ' Withdrawal Fee' :
                              '📱 ' + (fromAccountType === 'mpesa' ? 'M-Pesa' : 'Airtel Money') + ' PayBill Fee'
"></span>
                        <span class="text-xs font-normal text-gray-500 dark:text-gray-400 ml-1">(auto-calculated, editable)</span>
                    </label>

                    <div class="flex items-center gap-2">
                        <span class="text-gray-600 dark:text-gray-300 font-medium">KES</span>
                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            x-model.number="transactionFee"
                            @input="feeManuallyEdited = true"
                            class="w-40 border border-gray-300 dark:border-gray-600 rounded px-3 py-2 focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        >
                        <button
                            type="button"
                            x-show="feeManuallyEdited"
                            @click="feeManuallyEdited = false; calculateFee()"
                            class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline"
                        >
                            ↺ Reset to calculated
                        </button>
                    </div>

                    <p x-show="feeType === 'atm' && !feeManuallyEdited"
                       class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Default: KES 33.00 flat + 15% excise duty = KES 37.95
                    </p>
                    <p x-show="feeType === 'savings' && !feeManuallyEdited"
                       class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                        Enter the withdrawal fee charged by your savings provider.
                    </p>
                </div>

                <!-- Total Deduction -->
                <div
                    x-show="showFee && transactionFee > 0"
                    x-transition
                    class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded p-3 mb-4"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-sm font-medium text-indigo-800 dark:text-indigo-200">
                            Total Deduction from <span x-text="fromAccountName"></span>:
                        </span>
                        <span class="text-lg font-bold text-indigo-800 dark:text-indigo-200"
                              x-text="'KES ' + totalDeduction.toLocaleString()"></span>
                    </div>
                    <p class="text-xs text-indigo-600 dark:text-indigo-300 mt-1">
                        Transfer: KES <span x-text="parseFloat(amount || 0).toLocaleString()"></span>
                        + Fee: KES <span x-text="parseFloat(transactionFee || 0).toFixed(2)"></span>
                    </p>
                </div>

                <!-- Date -->
                <div class="mb-4">
                    <label for="date" class="block text-gray-700 dark:text-gray-200 font-semibold mb-2">Date</label>
                    <input type="datetime-local" name="date" id="date" x-model="date"
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
                date: '{{ old('date', now()->format('Y-m-d\TH:i')) }}',
                description: '{{ old('description') }}',
                transactionFee: {{ old('transaction_fee', 0) }},
                showFee: false,
                feeType: null,
                fromAccountType: '',
                fromAccountName: '',
                toAccountType: '',
                feeManuallyEdited: false,

                ATM_FEE: 33 + (33 * 0.15),

                get totalDeduction() {
                    return parseFloat(this.amount || 0) + parseFloat(this.transactionFee || 0);
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
                    if (this.feeManuallyEdited) return;

                    const fromAccount = this.getAccount(this.fromAccountId);
                    const toAccount   = this.getAccount(this.toAccountId);

                    if (!fromAccount || !toAccount || !this.amount || this.amount <= 0) {
                        this.showFee = false;
                        this.transactionFee = 0;
                        this.feeType = null;
                        return;
                    }

                    this.fromAccountType = fromAccount.type;
                    this.fromAccountName = fromAccount.name;
                    this.toAccountType   = toAccount.type;

                    // Savings transfers: show fee field at 0 for manual entry
                    if (fromAccount.type === 'savings') {
                        this.feeType        = 'savings';
                        this.transactionFee = 0;
                        this.showFee        = true;
                        return;
                    }

                    const mobileMoneyTypes = ['mpesa', 'airtel_money'];
                    const isMobileMoney    = mobileMoneyTypes.includes(fromAccount.type);

                    // M-Pesa/Airtel → Cash = Withdrawal fee
                    if (isMobileMoney && toAccount.type === 'cash') {
                        this.feeType        = 'withdrawal';
                        this.transactionFee = this.getWithdrawalFee(parseFloat(this.amount), fromAccount.type);
                        this.showFee        = this.transactionFee > 0;
                    }
                    // M-Pesa/Airtel → Bank = PayBill fee
                    else if (isMobileMoney && toAccount.type === 'bank') {
                        this.feeType        = 'paybill';
                        this.transactionFee = this.getPayBillFee(parseFloat(this.amount), fromAccount.type);
                        this.showFee        = this.transactionFee > 0;
                    }
                    // Bank → Cash = ATM fee
                    else if (fromAccount.type === 'bank' && toAccount.type === 'cash') {
                        this.feeType        = 'atm';
                        this.transactionFee = this.ATM_FEE;
                        this.showFee        = true;
                    }
                    // All other transfers = no fee
                    else {
                        this.feeType        = null;
                        this.transactionFee = 0;
                        this.showFee        = false;
                    }
                },
                getWithdrawalFee(amount, accountType) {
                    if (!amount || amount < 50) return 0;

                    const tiers = [
                        { min: 50,    max: 100,    cost: 11  },
                        { min: 101,   max: 500,    cost: 29  },
                        { min: 501,   max: 1000,   cost: 29  },
                        { min: 1001,  max: 1500,   cost: 29  },
                        { min: 1501,  max: 2500,   cost: 29  },
                        { min: 2501,  max: 3500,   cost: 52  },
                        { min: 3501,  max: 5000,   cost: 69  },
                        { min: 5001,  max: 7500,   cost: 87  },
                        { min: 7501,  max: 10000,  cost: 115 },
                        { min: 10001, max: 15000,  cost: 167 },
                        { min: 15001, max: 20000,  cost: 185 },
                        { min: 20001, max: 35000,  cost: 197 },
                        { min: 35001, max: 50000,  cost: 278 },
                        { min: 50001, max: 250000, cost: 309 },
                    ];

                    for (let tier of tiers) {
                        if (amount >= tier.min && amount <= tier.max) return tier.cost;
                    }
                    return amount > 250000 ? 309 : 0;
                },

                getPayBillFee(amount, accountType) {
                    if (!amount || amount <= 0) return 0;

                    if (accountType === 'airtel_money') return 0;

                    const tiers = [
                        { min: 1,     max: 49,     cost: 0   },
                        { min: 50,    max: 100,    cost: 0   },
                        { min: 101,   max: 500,    cost: 5   },
                        { min: 501,   max: 1000,   cost: 10  },
                        { min: 1001,  max: 1500,   cost: 15  },
                        { min: 1501,  max: 2500,   cost: 20  },
                        { min: 2501,  max: 3500,   cost: 25  },
                        { min: 3501,  max: 5000,   cost: 34  },
                        { min: 5001,  max: 7500,   cost: 42  },
                        { min: 7501,  max: 10000,  cost: 48  },
                        { min: 10001, max: 15000,  cost: 57  },
                        { min: 15001, max: 20000,  cost: 62  },
                        { min: 20001, max: 25000,  cost: 67  },
                        { min: 25001, max: 30000,  cost: 72  },
                        { min: 30001, max: 35000,  cost: 83  },
                        { min: 35001, max: 40000,  cost: 99  },
                        { min: 40001, max: 45000,  cost: 103 },
                        { min: 45001, max: 50000,  cost: 108 },
                        { min: 50001, max: 250000, cost: 108 },
                    ];

                    for (let tier of tiers) {
                        if (amount >= tier.min && amount <= tier.max) return tier.cost;
                    }
                    return amount > 250000 ? 108 : 0;
                },

                updateDestinationOptions() {
                    const fromAccount = this.getAccount(this.fromAccountId);
                    const toSelect    = document.getElementById('to_account_id');
                    if (!toSelect) return;

                    Array.from(toSelect.options).forEach(option => {
                        if (!option.value) return;

                        const optionType = option.dataset.type;

                        // Disable same account
                        if (option.value === this.fromAccountId) {
                            option.disabled = true;
                            option.hidden   = true;
                            return;
                        }

                        // Bank source: allow mpesa, airtel_money, cash
                        if (fromAccount && fromAccount.type === 'bank') {
                            const allowed = ['mpesa', 'airtel_money', 'cash'];
                            option.disabled = !allowed.includes(optionType);
                            option.hidden   = !allowed.includes(optionType);
                        }
                        // Savings source: allow mpesa, airtel_money, bank
                        else if (fromAccount && fromAccount.type === 'savings') {
                            const allowed = ['mpesa', 'airtel_money', 'bank'];
                            option.disabled = !allowed.includes(optionType);
                            option.hidden   = !allowed.includes(optionType);
                        }
                        else {
                            option.disabled = false;
                            option.hidden   = false;
                        }
                    });

                    // Auto-select M-Pesa if source is bank and no destination selected
                    if (fromAccount && fromAccount.type === 'bank' && !this.toAccountId) {
                        const mpesaOption = Array.from(toSelect.options).find(opt =>
                            opt.dataset.type === 'mpesa' && opt.value !== this.fromAccountId
                        );
                        if (mpesaOption) this.toAccountId = mpesaOption.value;
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
