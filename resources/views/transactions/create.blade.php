<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between mb-6">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add New Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Transactions
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto mt-10 p-6 bg-white dark:bg-gray-800 shadow-xl rounded-2xl">

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('transactions.store') }}" method="POST" class="space-y-5" x-data="transactionForm(@json($mpesaCosts), @json($airtelCosts))">
            @csrf

            <!-- Date -->
            <div>
                <label class="block font-semibold mb-1 dark:text-gray-200">Date</label>
                <input
                    type="date"
                    name="date"
                    value="{{ old('date', date('Y-m-d')) }}"
                    class="w-full border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    required
                >
            </div>

            <!-- Description -->
            <div>
                <label class="block font-semibold mb-1 dark:text-gray-200">Description</label>
                <input
                    type="text"
                    name="description"
                    value="{{ old('description') }}"
                    placeholder="e.g. Supermarket shopping"
                    class="w-full border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    required
                >
            </div>

            <!-- Account -->
            <div>
                <label class="block font-semibold mb-1 dark:text-gray-200">Account</label>
                <select
                    name="account_id"
                    x-model="accountId"
                    @change="onAccountChange()"
                    class="w-full border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    required
                >
                    <option value="">-- Select Account --</option>
                    @php
                        $mpesaAccount = $accounts->where('type', 'mpesa')->first();
                        $defaultAccountId = old('account_id') ?: ($mpesaAccount ? $mpesaAccount->id : null);
                    @endphp
                    @foreach($accounts as $account)
                        <option
                            value="{{ $account->id }}"
                            data-type="{{ $account->type }}"
                            {{ $defaultAccountId == $account->id ? 'selected' : '' }}
                        >
                            @if($account->type == 'cash') 💵
                            @elseif($account->type == 'mpesa') 📱
                            @elseif($account->type == 'airtel_money') 📲
                            @elseif($account->type == 'bank') 🏦
                            @endif
                            {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                        </option>
                    @endforeach
                </select>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Payment method will be auto-set based on account type</p>
            </div>

            <!-- Mobile Money Transaction Type (shown only for M-Pesa/Airtel Money) -->
            <div x-show="showTransactionTypeSelector" x-transition>
                <label class="block font-semibold mb-1 dark:text-gray-200">Transaction Type</label>
                <select
                    name="mobile_money_type"
                    x-model="mobileMoneyType"
                    @change="calculateTransactionCost()"
                    class="w-full border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                >
                    <option value="send_money">Send Money (Person to Person)</option>
                    <option value="paybill">PayBill (No Charges)</option>
                    <option value="buy_goods">Buy Goods/Till (No Charges)</option>
                    <option value="withdraw">Withdraw from Agent</option>
                </select>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Different transaction types have different fees
                </p>
            </div>

            <!-- Amount -->
            <div>
                <label class="block font-semibold mb-1 dark:text-gray-200">Amount</label>
                <input
                    type="number"
                    step="0.01"
                    name="amount"
                    value="{{ old('amount') }}"
                    x-model="amount"
                    @input="calculateTransactionCost()"
                    class="w-full border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    required
                >
            </div>

            <!-- Transaction Cost (Auto-calculated) -->
            <div x-show="showTransactionCost" x-transition class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                <div class="flex items-center justify-between mb-2">
                    <label class="block font-semibold text-yellow-800 dark:text-yellow-200">
                        <span x-text="accountTypeName"></span> <span x-text="getTransactionTypeLabel()"></span> Fee
                    </label>
                    <span class="text-lg font-bold text-yellow-800 dark:text-yellow-200" x-text="'KSh ' + transactionCost.toFixed(2)"></span>
                </div>
                <input type="hidden" name="transaction_cost" :value="transactionCost">
                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                    This fee will be automatically added as a separate transaction under "Transaction Fees" category.
                </p>
            </div>

            <!-- Total Amount Display -->
            <div x-show="showTransactionCost && amount > 0" x-transition class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded-lg p-4">
                <div class="flex items-center justify-between">
                    <span class="font-semibold text-indigo-800 dark:text-indigo-200">Total Amount (including fee):</span>
                    <span class="text-xl font-bold text-indigo-800 dark:text-indigo-200" x-text="'KSh ' + totalAmount.toFixed(2)"></span>
                </div>
            </div>

            <!-- Category -->
            <div>
                <label class="block font-semibold mb-1 dark:text-gray-200">Category</label>
                <div
                    x-data='categoryDropdown(@json($categoryGroups), @json(old("category_id")))'
                    x-init="init()"
                    class="relative w-full"
                    @click.outside="closeDropdown()"
                    x-id="['category-dropdown']"
                >
                    <!-- Display/Search Input -->
                    <div class="relative">
                        <input
                            type="text"
                            x-model="search"
                            @input="handleInput()"
                            @focus="open = true"
                            @keydown.escape="closeDropdown()"
                            @keydown.enter.prevent="selectFirst()"
                            placeholder="Search or select a category..."
                            class="w-full border dark:border-gray-600 p-2 pr-20 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                            :aria-owns="$id('category-dropdown')"
                            :aria-expanded="open"
                            aria-label="Category"
                            autocomplete="off"
                        >

                        <!-- Clear & Dropdown Icons -->
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            <!-- Clear button (shows when category is selected) -->
                            <button
                                type="button"
                                x-show="selectedId"
                                @click.stop="clear()"
                                class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition"
                                title="Clear selection"
                            >
                                <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                            </button>

                            <!-- Dropdown arrow -->
                            <button
                                type="button"
                                @click.stop="toggle()"
                                class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition"
                            >
                                <svg
                                    class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform"
                                    :class="{ 'rotate-180': open }"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Dropdown Menu -->
                    <div
                        x-show="open"
                        x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="opacity-0 scale-95"
                        x-transition:enter-end="opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="opacity-100 scale-100"
                        x-transition:leave-end="opacity-0 scale-95"
                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border dark:border-gray-600 rounded-lg shadow-lg max-h-64 overflow-auto"
                        :id="$id('category-dropdown')"
                        role="listbox"
                        style="display: none;"
                    >
                        <template x-for="parent in filteredCategories()" :key="parent.id">
                            <div>
                                <!-- Parent Category Header -->
                                <div class="px-4 py-2 font-semibold text-sm text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 sticky top-0">
                                    <span x-show="parent.icon" x-text="parent.icon" class="mr-1"></span>
                                    <span x-text="parent.name"></span>
                                </div>

                                <!-- Child Categories -->
                                <template x-for="child in parent.children" :key="child.id">
                                    <div
                                        @click="select(child)"
                                        class="px-6 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer text-gray-700 dark:text-gray-200 transition"
                                        :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedId === child.id }"
                                        role="option"
                                        :aria-selected="selectedId === child.id"
                                    >
                                        <span x-show="child.icon" x-text="child.icon" class="mr-2"></span>
                                        <span x-text="child.name"></span>
                                    </div>
                                </template>

                                <!-- Parent as selectable option (if no children) -->
                                <template x-if="parent.children.length === 0">
                                    <div
                                        @click="select(parent)"
                                        class="px-6 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer text-gray-700 dark:text-gray-200 transition"
                                        :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedId === parent.id }"
                                        role="option"
                                        :aria-selected="selectedId === parent.id"
                                    >
                                        <span x-show="parent.icon" x-text="parent.icon" class="mr-2"></span>
                                        <span x-text="parent.name"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        <!-- No results message -->
                        <div x-show="filteredCategories().length === 0" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400 text-sm">
                            No categories found
                        </div>
                    </div>

                    <!-- Hidden input for form submission -->
                    <input type="hidden" name="category_id" :value="selectedId" required>
                </div>
            </div>

            <!-- Submit -->
            <div class="flex items-center justify-between">
                <a href="{{ route('transactions.index') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200">Cancel</a>
                <button type="submit" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700 transition">
                    Save Transaction
                </button>
            </div>
        </form>
    </div>

    <script>
        function transactionForm(mpesaCosts, airtelCosts) {
            return {
                amount: {{ old('amount', 0) }},
                accountId: '{{ old('account_id', $mpesaAccount->id ?? '') }}',
                accountType: '',
                accountTypeName: '',
                mobileMoneyType: '{{ old('mobile_money_type', 'send_money') }}',
                transactionCost: 0,
                showTransactionCost: false,
                showTransactionTypeSelector: false,
                mpesaCosts: mpesaCosts,
                airtelCosts: airtelCosts,

                get totalAmount() {
                    return parseFloat(this.amount || 0) + parseFloat(this.transactionCost || 0);
                },

                init() {
                    this.onAccountChange();
                },

                onAccountChange() {
                    const select = document.querySelector('select[name="account_id"]');
                    const selectedOption = select.options[select.selectedIndex];

                    if (selectedOption && selectedOption.value) {
                        this.accountType = selectedOption.getAttribute('data-type');

                        // Show transaction type selector only for mobile money accounts
                        if (this.accountType === 'mpesa' || this.accountType === 'airtel_money') {
                            this.showTransactionTypeSelector = true;
                        } else {
                            this.showTransactionTypeSelector = false;
                            this.mobileMoneyType = 'send_money';
                        }

                        this.calculateTransactionCost();
                    } else {
                        this.accountType = '';
                        this.showTransactionCost = false;
                        this.showTransactionTypeSelector = false;
                        this.transactionCost = 0;
                    }
                },

                getTransactionTypeLabel() {
                    const labels = {
                        'send_money': 'Send Money',
                        'paybill': 'PayBill',
                        'buy_goods': 'Buy Goods/Till',
                        'withdraw': 'Withdraw'
                    };
                    return labels[this.mobileMoneyType] || 'Transaction';
                },

                calculateTransactionCost() {
                    const amount = parseFloat(this.amount || 0);

                    if (!this.accountType || amount <= 0) {
                        this.showTransactionCost = false;
                        this.transactionCost = 0;
                        return;
                    }

                    let cost = 0;
                    let costs = {};

                    if (this.accountType === 'mpesa') {
                        costs = this.mpesaCosts[this.mobileMoneyType] || this.mpesaCosts['send_money'];
                        this.accountTypeName = 'M-Pesa';
                        this.showTransactionCost = true;
                    } else if (this.accountType === 'airtel_money') {
                        costs = this.airtelCosts[this.mobileMoneyType] || this.airtelCosts['send_money'];
                        this.accountTypeName = 'Airtel Money';
                        this.showTransactionCost = true;
                    } else {
                        this.showTransactionCost = false;
                        this.transactionCost = 0;
                        return;
                    }

                    // Find the appropriate cost tier
                    if (Array.isArray(costs)) {
                        for (let tier of costs) {
                            if (amount >= tier.min && amount <= tier.max) {
                                cost = tier.cost;
                                break;
                            }
                        }
                    }

                    this.transactionCost = cost;

                    // Hide the cost display if it's 0 (like for paybill/buy goods)
                    if (cost === 0 && (this.mobileMoneyType === 'paybill' || this.mobileMoneyType === 'buy_goods')) {
                        this.showTransactionCost = false;
                    }
                }
            }
        }

        function categoryDropdown(categories, oldId = null) {
            return {
                open: false,
                search: '',
                selectedId: oldId,
                selectedName: '',
                isSearching: false,
                categories: categories.map(parent => ({
                    ...parent,
                    children: parent.children || []
                })),

                toggle() {
                    this.open = !this.open;
                    if (this.open) {
                        this.isSearching = false;
                        this.search = '';
                    }
                },

                closeDropdown() {
                    this.open = false;
                    // Restore selected name if not searching
                    if (!this.isSearching && this.selectedName) {
                        this.search = this.selectedName;
                    }
                },

                handleInput() {
                    this.isSearching = true;
                    this.open = true;
                },

                select(category) {
                    this.selectedId = category.id;
                    this.selectedName = category.name;
                    this.search = category.name;
                    this.isSearching = false;
                    this.open = false;
                },

                clear() {
                    this.selectedId = null;
                    this.selectedName = '';
                    this.search = '';
                    this.isSearching = false;
                    this.open = true;
                },

                selectFirst() {
                    const filtered = this.filteredCategories();
                    if (filtered.length > 0) {
                        const firstCategory = filtered[0].children.length > 0
                            ? filtered[0].children[0]
                            : filtered[0];
                        this.select(firstCategory);
                    }
                },

                filteredCategories() {
                    if (!this.search || !this.isSearching) {
                        return this.categories;
                    }

                    const searchLower = this.search.toLowerCase();

                    return this.categories.filter(parent => {
                        const parentMatches = parent.name.toLowerCase().includes(searchLower);
                        const matchingChildren = parent.children.filter(child =>
                            child.name.toLowerCase().includes(searchLower)
                        );
                        return parentMatches || matchingChildren.length > 0;
                    }).map(parent => ({
                        ...parent,
                        children: parent.children.filter(child =>
                            child.name.toLowerCase().includes(searchLower)
                        )
                    }));
                },

                init() {
                    // Set initial selected category name
                    if (this.selectedId) {
                        for (const parent of this.categories) {
                            // Check children first
                            const child = parent.children.find(c => c.id === this.selectedId);
                            if (child) {
                                this.selectedName = child.name;
                                this.search = child.name;
                                return;
                            }
                            // Check if parent is selected
                            if (parent.id === this.selectedId) {
                                this.selectedName = parent.name;
                                this.search = parent.name;
                                return;
                            }
                        }
                    }
                }
            }
        }
    </script>
</x-app-layout>
