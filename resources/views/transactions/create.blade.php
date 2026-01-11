<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <h2 class="font-semibold text-base sm:text-lg md:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Add New Transaction') }}
            </h2>
            <a href="{{ route('transactions.index') }}" class="text-sm sm:text-base text-indigo-600 hover:text-indigo-800">
                ← Back to Transactions
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto mt-4 sm:mt-10 p-4 sm:p-6 bg-white dark:bg-gray-800 shadow-xl rounded-xl sm:rounded-2xl">

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-3 sm:p-4 rounded text-sm mb-4 sm:mb-6">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-3 sm:p-4 rounded text-sm mb-4 sm:mb-6">
                {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('transactions.store') }}" method="POST" class="space-y-4 sm:space-y-5">
            @csrf

            <div x-data="transactionForm()">
                <!-- Date -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Date</label>
                    <input
                        type="date"
                        name="date"
                        value="{{ old('date', date('Y-m-d')) }}"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                </div>

                <!-- Description -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Description</label>
                    <input
                        type="text"
                        name="description"
                        value="{{ old('description') }}"
                        placeholder="e.g. Supermarket shopping"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                </div>

                <!-- Category -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Category</label>
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
                                class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 pr-16 sm:pr-20 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                                :aria-owns="$id('category-dropdown')"
                                :aria-expanded="open"
                                aria-label="Category"
                                autocomplete="off"
                            >

                            <!-- Clear & Dropdown Icons -->
                            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <!-- Clear button -->
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
                            x-transition
                            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border dark:border-gray-600 rounded-lg shadow-lg max-h-60 sm:max-h-64 overflow-auto"
                            :id="$id('category-dropdown')"
                            role="listbox"
                            style="display: none;"
                        >
                            <template x-for="child in filteredChildren()" :key="child.id">
                                <div
                                    @click="select(child)"
                                    class="px-4 sm:px-6 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer text-sm sm:text-base text-gray-700 dark:text-gray-200 transition"
                                    :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedId === child.id }"
                                    role="option"
                                    :aria-selected="selectedId === child.id"
                                >
                                    <span x-show="child.icon" x-text="child.icon" class="mr-2"></span>
                                    <span x-text="child.name"></span>
                                </div>
                            </template>

                            <!-- No results message -->
                            <div x-show="filteredChildren().length === 0" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400 text-xs sm:text-sm">
                                No categories found
                            </div>
                        </div>

                        <!-- Hidden input for form submission -->
                        <input type="hidden" name="category_id" :value="selectedId" required>
                    </div>
                </div>

                <!-- Account -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Account</label>
                    <select
                        name="account_id"
                        x-model="accountId"
                        @change="onAccountChange()"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
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
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Payment method will be auto-set based on account type</p>
                </div>

                <!-- Mobile Money Transaction Type -->
                <div class="mb-4 sm:mb-5" x-show="showTransactionTypeSelector">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Transaction Type</label>
                    <select
                        name="mobile_money_type"
                        x-model="mobileMoneyType"
                        @change="calculateTransactionCost()"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                    >
                        <option value="send_money">Send Money</option>
                        <option value="paybill">PayBill</option>
                        <option value="buy_goods">Buy Goods/Till Number</option>
                        <option value="pochi_la_biashara">Pochi La Biashara</option>
                    </select>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">
                        <span x-show="mobileMoneyType === 'send_money' || mobileMoneyType === 'pochi_la_biashara'">Different types have different fees</span>
                        <span x-show="mobileMoneyType === 'paybill'">PayBill has lower fees</span>
                        <span x-show="mobileMoneyType === 'buy_goods'">Till has no charges</span>
                    </p>
                </div>

                <!-- Amount -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        value="{{ old('amount') }}"
                        x-model="amount"
                        @input="calculateTransactionCost()"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                </div>

                <!-- Zero Fee Notice for Internet and Communication -->
                <div x-show="isInternetAndCommunication && (accountType === 'mpesa' || accountType === 'airtel_money') && amount > 0" class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded p-2 sm:p-3 mb-4 sm:mb-5">
                    <div class="flex items-start gap-2">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div>
                            <p class="text-sm font-medium text-green-800 dark:text-green-200">No Transaction Fees</p>
                            <p class="text-xs text-green-700 dark:text-green-300 mt-0.5">Internet and Communication transactions have zero fees</p>
                        </div>
                    </div>
                </div>

                <!-- Transaction Cost -->
                <div x-show="showTransactionCost && amount > 0 && !isInternetAndCommunication" class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded p-2 sm:p-3 mb-4 sm:mb-5">
                    <div class="flex items-center justify-between">
                        <label class="text-xs font-medium text-yellow-800 dark:text-yellow-200">
                            <span x-text="accountTypeName"></span> Fee
                            <span x-show="transactionCost === 0" class="text-green-600 dark:text-green-400">(Free)</span>
                        </label>
                        <span class="text-base sm:text-lg font-bold text-yellow-800 dark:text-yellow-200" x-text="'KSh ' + transactionCost.toFixed(2)"></span>
                    </div>
                    <input type="hidden" name="transaction_cost" :value="transactionCost">
                </div>

                <!-- Total Amount Display -->
                <div x-show="amount > 0 && (accountType === 'mpesa' || accountType === 'airtel_money')" class="bg-indigo-50 dark:bg-indigo-900/20 border border-indigo-200 dark:border-indigo-800 rounded p-2 sm:p-3 mb-4 sm:mb-5">
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-indigo-800 dark:text-indigo-200">Total Amount:</span>
                        <span class="text-base sm:text-lg font-bold text-indigo-800 dark:text-indigo-200" x-text="'KSh ' + totalAmount.toFixed(2)"></span>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2">
                    <a href="{{ route('transactions.index') }}" class="text-center sm:text-left text-sm sm:text-base text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 py-2 sm:py-0">
                        Cancel
                    </a>
                    <button type="submit" class="w-full sm:w-auto bg-indigo-600 text-white px-6 py-2.5 sm:py-2 rounded-lg hover:bg-indigo-700 transition text-sm sm:text-base font-medium">
                        Save Transaction
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function transactionForm() {
            return {
                amount: {{ old('amount', 0) }},
                accountId: '{{ old('account_id', $mpesaAccount->id ?? '') }}',
                accountType: '',
                accountTypeName: '',
                mobileMoneyType: '{{ old('mobile_money_type', 'send_money') }}',
                transactionCost: 0,
                showTransactionCost: false,
                showTransactionTypeSelector: false,
                selectedCategoryName: '',
                mpesaCosts: @json($mpesaCosts),
                airtelCosts: @json($airtelCosts),
                categories: @json($categoryGroups),

                get totalAmount() {
                    return parseFloat(this.amount || 0) + parseFloat(this.transactionCost || 0);
                },

                get isInternetAndCommunication() {
                    return this.selectedCategoryName === 'Internet and Communication';
                },

                init() {
                    // Initialize category name if old value exists
                    const oldCategoryId = '{{ old("category_id") }}';
                    if (oldCategoryId) {
                        this.updateCategoryName(parseInt(oldCategoryId));
                    }

                    // Listen for category changes
                    window.addEventListener('category-selected', (e) => {
                        this.selectedCategoryName = e.detail.categoryName;
                        this.calculateTransactionCost();
                    });

                    this.$nextTick(() => {
                        this.onAccountChange();
                    });
                },

                onAccountChange() {
                    const select = document.querySelector('select[name="account_id"]');
                    if (!select || !select.value) {
                        this.accountType = '';
                        this.showTransactionCost = false;
                        this.showTransactionTypeSelector = false;
                        this.transactionCost = 0;
                        return;
                    }

                    const selectedOption = select.options[select.selectedIndex];
                    this.accountType = selectedOption.getAttribute('data-type');

                    // Show transaction type selector for mobile money accounts
                    if (this.accountType === 'mpesa' || this.accountType === 'airtel_money') {
                        this.showTransactionTypeSelector = true;

                        // Hide Pochi La Biashara for Airtel Money
                        if (this.accountType === 'airtel_money' && this.mobileMoneyType === 'pochi_la_biashara') {
                            this.mobileMoneyType = 'send_money';
                        }
                    } else {
                        this.showTransactionTypeSelector = false;
                        this.mobileMoneyType = 'send_money';
                    }

                    this.calculateTransactionCost();
                },

                updateCategoryName(categoryId) {
                    for (const parent of this.categories) {
                        if (parent.id === categoryId) {
                            this.selectedCategoryName = parent.name;
                            return;
                        }
                        if (parent.children) {
                            const child = parent.children.find(c => c.id === categoryId);
                            if (child) {
                                this.selectedCategoryName = child.name;
                                return;
                            }
                        }
                    }
                },

                calculateTransactionCost() {
                    const amount = parseFloat(this.amount || 0);

                    if (!this.accountType || amount <= 0) {
                        this.showTransactionCost = false;
                        this.transactionCost = 0;
                        return;
                    }

                    // Not a mobile money account
                    if (this.accountType !== 'mpesa' && this.accountType !== 'airtel_money') {
                        this.showTransactionCost = false;
                        this.transactionCost = 0;
                        return;
                    }

                    // Special case: Internet and Communication has zero fees
                    if (this.isInternetAndCommunication) {
                        this.transactionCost = 0;
                        this.showTransactionCost = false;
                        return;
                    }

                    let cost = 0;
                    let costs = [];

                    if (this.accountType === 'mpesa') {
                        costs = this.mpesaCosts[this.mobileMoneyType] || this.mpesaCosts['send_money'];
                        this.accountTypeName = 'M-Pesa';
                        this.showTransactionCost = true;
                    } else if (this.accountType === 'airtel_money') {
                        costs = this.airtelCosts[this.mobileMoneyType] || this.airtelCosts['send_money'];
                        this.accountTypeName = 'Airtel Money';
                        this.showTransactionCost = true;
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

                    // Hide cost display if it's 0 for buy_goods
                    if (cost === 0 && this.mobileMoneyType === 'buy_goods') {
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

                    // Dispatch event for transaction form
                    window.dispatchEvent(new CustomEvent('category-selected', {
                        detail: {
                            categoryId: category.id,
                            categoryName: category.name
                        }
                    }));
                },

                clear() {
                    this.selectedId = null;
                    this.selectedName = '';
                    this.search = '';
                    this.isSearching = false;
                    this.open = true;

                    // Dispatch event
                    window.dispatchEvent(new CustomEvent('category-selected', {
                        detail: {
                            categoryId: null,
                            categoryName: ''
                        }
                    }));
                },

                selectFirst() {
                    const filtered = this.filteredChildren();
                    if (filtered.length > 0) {
                        this.select(filtered[0]);
                    }
                },

                filteredChildren() {
                    // Flatten all children from all parents into a single list
                    let allChildren = [];

                    for (const parent of this.categories) {
                        if (parent.children && parent.children.length > 0) {
                            allChildren = allChildren.concat(parent.children);
                        }
                    }

                    // If not searching, return all children
                    if (!this.search || !this.isSearching) {
                        return allChildren;
                    }

                    // Filter by search term
                    const searchLower = this.search.toLowerCase();
                    return allChildren.filter(child =>
                        child.name.toLowerCase().includes(searchLower)
                    );
                },

                init() {
                    if (this.selectedId) {
                        for (const parent of this.categories) {
                            const child = parent.children.find(c => c.id === this.selectedId);
                            if (child) {
                                this.selectedName = child.name;
                                this.search = child.name;

                                // Notify transaction form
                                window.dispatchEvent(new CustomEvent('category-selected', {
                                    detail: {
                                        categoryId: child.id,
                                        categoryName: child.name
                                    }
                                }));
                                return;
                            }
                            if (parent.id === this.selectedId) {
                                this.selectedName = parent.name;
                                this.search = parent.name;

                                // Notify transaction form
                                window.dispatchEvent(new CustomEvent('category-selected', {
                                    detail: {
                                        categoryId: parent.id,
                                        categoryName: parent.name
                                    }
                                }));
                                return;
                            }
                        }
                    }
                }
            }
        }
    </script>
</x-app-layout>
