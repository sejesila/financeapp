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
                        x-data='categoryDropdown(@json($categoryGroups), @json($allCategoriesArray), @json(old("category_id")))'
                        x-init="init()"
                        class="relative w-full"
                        @click.outside="closeDropdown()"
                        x-id="['category-dropdown']"
                    >
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
                            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <button type="button" x-show="selectedId" @click.stop="clear()"
                                        class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition" title="Clear selection">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                    </svg>
                                </button>
                                <button type="button" @click.stop="toggle()" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div x-show="open" x-transition
                             class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-700 border dark:border-gray-600 rounded-lg shadow-lg max-h-60 sm:max-h-64 overflow-auto"
                             :id="$id('category-dropdown')" role="listbox" style="display: none;">
                            <template x-for="child in filteredChildren()" :key="child.id">
                                <div @click="select(child)"
                                     class="px-4 sm:px-6 py-2 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 cursor-pointer text-sm sm:text-base text-gray-700 dark:text-gray-200 transition"
                                     :class="{ 'bg-indigo-100 dark:bg-indigo-900/50': selectedId === child.id }"
                                     role="option" :aria-selected="selectedId === child.id">
                                    <span x-show="child.icon" x-text="child.icon" class="mr-2"></span>
                                    <span x-text="child.name"></span>
                                </div>
                            </template>
                            <div x-show="filteredChildren().length === 0" class="px-4 py-3 text-center text-gray-500 dark:text-gray-400 text-xs sm:text-sm">
                                No categories found
                            </div>
                        </div>
                        <input type="hidden" name="category_id" :value="selectedId" required>
                    </div>
                </div>

                <!-- Total Amount -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Total Amount</label>
                    <input
                        type="number"
                        step="0.01"
                        name="amount"
                        x-model="totalAmount"
                        @input="onTotalAmountChange()"
                        value="{{ old('amount') }}"
                        placeholder="Enter total amount"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
                </div>

                <!-- Split Payment Toggle -->
                <div class="mb-4 sm:mb-5">
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <div class="relative">
                            <input type="checkbox" class="sr-only" x-model="isSplit" @change="onSplitToggle()">
                            <div class="w-10 h-6 rounded-full transition-colors duration-200"
                                 :class="isSplit ? 'bg-indigo-600' : 'bg-gray-300 dark:bg-gray-600'"></div>
                            <div class="absolute top-1 left-1 w-4 h-4 bg-white rounded-full shadow transition-transform duration-200"
                                 :class="isSplit ? 'translate-x-4' : 'translate-x-0'"></div>
                        </div>
                        <span class="text-sm sm:text-base font-semibold dark:text-gray-200">Split payment across multiple accounts</span>
                    </label>
                    <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1 ml-13">
                        e.g. pay part in cash, part via M-Pesa
                    </p>
                </div>

                <!-- SINGLE PAYMENT (default) -->
                <div x-show="!isSplit" x-transition>
                    <!-- Account -->
                    <div class="mb-4 sm:mb-5">
                        <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Account</label>
                        <select
                            name="account_id"
                            x-model="accountId"
                            @change="onAccountChange()"
                            class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                            :required="!isSplit"
                        >
                            <option value="">-- Select Account --</option>
                            @php
                                $mpesaAccount = $accounts->where('type', 'mpesa')->first();
                                $defaultAccountId = old('account_id') ?: ($mpesaAccount ? $mpesaAccount->id : null);
                            @endphp
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" data-type="{{ $account->type }}"
                                    {{ $defaultAccountId == $account->id ? 'selected' : '' }}>
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

                    <!-- Mobile Money Transaction Type (single) -->
                    <div class="mb-4 sm:mb-5" x-show="showTransactionTypeSelector">
                        <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Transaction Type</label>
                        <select
                            name="mobile_money_type"
                            x-model="mobileMoneyType"
                            class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        >
                            <template x-for="type in transactionTypeOptions" :key="type.key">
                                <option :value="type.key" x-text="type.label"></option>
                            </template>
                        </select>
                        <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mt-1">Sorted by frequency of use</p>
                    </div>
                </div>

                <!-- SPLIT PAYMENT -->
                <div x-show="isSplit" x-transition>
                    <template x-if="isSplit">
                        <input type="hidden" name="is_split" value="1">
                    </template>

                    <div class="mb-3">
                        <div class="flex items-center justify-between mb-2">
                            <label class="block text-sm sm:text-base font-semibold dark:text-gray-200">Payment Splits</label>
                            <span class="text-xs sm:text-sm font-medium"
                                  :class="splitsBalanced ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'">
                                <span x-text="formatAmount(splitTotal)"></span>
                                /
                                <span x-text="formatAmount(parseFloat(totalAmount) || 0)"></span>
                                <span x-show="splitsBalanced" class="ml-1">✓</span>
                                <span x-show="!splitsBalanced && splitTotal > 0" class="ml-1">
                                    (<span x-text="formatAmount(Math.abs((parseFloat(totalAmount) || 0) - splitTotal))"></span>
                                    <span x-text="splitTotal > (parseFloat(totalAmount) || 0) ? 'over' : 'remaining'"></span>)
                                </span>
                            </span>
                        </div>

                        <!-- Split rows -->
                        <div class="space-y-3">
                            <template x-for="(split, index) in splits" :key="split.id">
                                <div class="border dark:border-gray-600 rounded-lg p-3 bg-gray-50 dark:bg-gray-700/50 space-y-2">
                                    <div class="flex items-center justify-between">
                                        <span class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide"
                                              x-text="'Payment ' + (index + 1)"></span>
                                        <button type="button" @click="removeSplit(index)"
                                                x-show="splits.length > 2"
                                                class="text-red-500 hover:text-red-700 text-xs flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                            Remove
                                        </button>
                                    </div>

                                    <select
                                        :name="'splits[' + index + '][account_id]'"
                                        x-model="split.account_id"
                                        @change="onSplitAccountChange(index)"
                                        class="w-full text-sm border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                                        :required="isSplit"
                                    >
                                        <option value="">-- Select Account --</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->id }}" data-type="{{ $account->type }}">
                                                @if($account->type == 'cash') 💵
                                                @elseif($account->type == 'mpesa') 📱
                                                @elseif($account->type == 'airtel_money') 📲
                                                @elseif($account->type == 'bank') 🏦
                                                @endif
                                                {{ $account->name }} ({{ number_format($account->current_balance, 0, '.', ',') }})
                                            </option>
                                        @endforeach
                                    </select>

                                    <div x-show="split.showMobileType">
                                        <select
                                            :name="'splits[' + index + '][mobile_money_type]'"
                                            x-model="split.mobile_money_type"
                                            class="w-full text-sm border dark:border-gray-600 p-2 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                                        >
                                            <template x-for="type in split.typeOptions" :key="type.key">
                                                <option :value="type.key" x-text="type.label"></option>
                                            </template>
                                        </select>
                                    </div>

                                    <div class="relative">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-500 dark:text-gray-400 text-sm font-medium">KSh</span>
                                        <input
                                            type="number"
                                            step="0.01"
                                            :name="'splits[' + index + '][amount]'"
                                            x-model="split.amount"
                                            @input="onSplitAmountChange()"
                                            placeholder="0.00"
                                            class="w-full text-sm border dark:border-gray-600 p-2 pl-10 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                                            :required="isSplit"
                                        >
                                    </div>
                                </div>
                            </template>
                        </div>

                        <button type="button" @click="addSplit()"
                                class="mt-3 w-full border-2 border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400 hover:border-indigo-400 hover:text-indigo-500 dark:hover:border-indigo-500 dark:hover:text-indigo-400 rounded-lg py-2 text-sm font-medium transition flex items-center justify-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
                            </svg>
                            Add Another Payment Method
                        </button>

                        <div x-show="isSplit && !splitsBalanced && splitTotal > 0 && totalAmount > 0"
                             class="mt-2 text-xs text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 rounded p-2">
                            ⚠️ Split amounts must add up to the total amount before saving.
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2">
                    <a href="{{ route('transactions.index') }}"
                       class="text-center sm:text-left text-sm sm:text-base text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 py-2 sm:py-0">
                        Cancel
                    </a>
                    <button type="submit"
                            :disabled="isSplit && !splitsBalanced"
                            class="w-full sm:w-auto bg-indigo-600 text-white px-6 py-2.5 sm:py-2 rounded-lg hover:bg-indigo-700 transition text-sm sm:text-base font-medium disabled:opacity-50 disabled:cursor-not-allowed">
                        Save Transaction
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function transactionForm() {
            return {
                totalAmount: {{ old('amount') ?: 'null' }},
                accountId: '{{ old('account_id', $mpesaAccount->id ?? '') }}',
                accountType: '',
                mobileMoneyType: '{{ old('mobile_money_type') }}',
                showTransactionTypeSelector: false,
                defaultMpesaType: '{{ $defaultMpesaType ?? 'send_money' }}',
                defaultAirtelType: '{{ $defaultAirtelType ?? 'send_money' }}',
                transactionTypeOptions: [],
                mpesaTypes: @json($mpesaTransactionTypes),
                airtelTypes: @json($airtelTransactionTypes),

                isSplit: false,
                splits: [],
                splitIdCounter: 0,

                get splitTotal() {
                    return this.splits.reduce((sum, s) => sum + (parseFloat(s.amount) || 0), 0);
                },

                get splitsBalanced() {
                    const total = parseFloat(this.totalAmount) || 0;
                    if (total <= 0 || this.splitTotal <= 0) return false;
                    return Math.abs(this.splitTotal - total) < 0.01;
                },

                init() {
                    this.$nextTick(() => {
                        this.onAccountChange();
                    });
                },

                formatAmount(val) {
                    return 'KSh ' + Number(val).toLocaleString('en-KE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                },

                onTotalAmountChange() {
                    if (this.isSplit && this.splits.length >= 2) {
                        const total = parseFloat(this.totalAmount) || 0;
                        const otherSum = this.splits.slice(0, -1).reduce((s, sp) => s + (parseFloat(sp.amount) || 0), 0);
                        const remainder = Math.max(0, total - otherSum);
                        this.splits[this.splits.length - 1].amount = remainder > 0 ? remainder.toFixed(2) : '';
                    }
                },

                onSplitToggle() {
                    if (this.isSplit && this.splits.length === 0) {
                        this.addSplit();
                        this.addSplit();
                        this.autoDistribute();
                    }
                },

                autoDistribute() {
                    const total = parseFloat(this.totalAmount) || 0;
                    if (total > 0 && this.splits.length > 0) {
                        const perSplit = (total / this.splits.length).toFixed(2);
                        const lastSplit = (total - perSplit * (this.splits.length - 1)).toFixed(2);
                        this.splits.forEach((s, i) => {
                            s.amount = i === this.splits.length - 1 ? lastSplit : perSplit;
                        });
                    }
                },

                addSplit() {
                    this.splits.push({
                        id: ++this.splitIdCounter,
                        account_id: '',
                        amount: '',
                        mobile_money_type: 'send_money',
                        showMobileType: false,
                        typeOptions: [],
                    });
                },

                removeSplit(index) {
                    this.splits.splice(index, 1);
                    this.onSplitAmountChange();
                },

                onSplitAmountChange() {
                    const total = parseFloat(this.totalAmount) || 0;
                    if (total > 0 && this.splits.length >= 2) {
                        const otherSum = this.splits.slice(0, -1).reduce((s, sp) => s + (parseFloat(sp.amount) || 0), 0);
                        const remainder = parseFloat((total - otherSum).toFixed(2));
                        if (remainder >= 0) {
                            this.splits[this.splits.length - 1].amount = remainder;
                        }
                    }
                },

                onSplitAccountChange(index) {
                    const split = this.splits[index];
                    const selectEl = document.querySelectorAll('select[name^="splits["][name$="[account_id]"]')[index];
                    if (!selectEl || !split) return;

                    const selectedOption = selectEl.options[selectEl.selectedIndex];
                    const accountType = selectedOption.getAttribute('data-type');

                    if (accountType === 'mpesa') {
                        split.showMobileType = true;
                        split.typeOptions = this.mpesaTypes;
                        split.mobile_money_type = this.defaultMpesaType;
                    } else if (accountType === 'airtel_money') {
                        split.showMobileType = true;
                        split.typeOptions = this.airtelTypes;
                        split.mobile_money_type = this.defaultAirtelType;
                    } else {
                        split.showMobileType = false;
                        split.typeOptions = [];
                        split.mobile_money_type = '';
                    }
                },

                onAccountChange() {
                    const select = document.querySelector('select[name="account_id"]');
                    if (!select || !select.value) {
                        this.accountType = '';
                        this.showTransactionTypeSelector = false;
                        return;
                    }

                    const selectedOption = select.options[select.selectedIndex];
                    this.accountType = selectedOption.getAttribute('data-type');

                    if (this.accountType === 'mpesa' || this.accountType === 'airtel_money') {
                        this.showTransactionTypeSelector = true;
                        if (this.accountType === 'mpesa') {
                            this.transactionTypeOptions = this.mpesaTypes;
                            if (!this.mobileMoneyType) this.mobileMoneyType = this.defaultMpesaType;
                        } else {
                            this.transactionTypeOptions = this.airtelTypes;
                            if (!this.mobileMoneyType) this.mobileMoneyType = this.defaultAirtelType;
                            if (this.mobileMoneyType === 'pochi_la_biashara') this.mobileMoneyType = 'send_money';
                        }
                    } else {
                        this.showTransactionTypeSelector = false;
                        this.mobileMoneyType = 'send_money';
                        this.transactionTypeOptions = [];
                    }
                }
            }
        }

        function categoryDropdown(categoryGroups, allCategoriesArray, oldId = null) {
            return {
                open: false,
                search: '',
                selectedId: oldId,
                selectedName: '',
                isSearching: false,
                categories: categoryGroups,
                allCategoriesSorted: allCategoriesArray,

                toggle() {
                    this.open = !this.open;
                    if (this.open) { this.isSearching = false; this.search = ''; }
                },

                closeDropdown() {
                    this.open = false;
                    if (!this.isSearching && this.selectedName) this.search = this.selectedName;
                },

                handleInput() { this.isSearching = true; this.open = true; },

                select(category) {
                    this.selectedId = category.id;
                    this.selectedName = category.name;
                    this.search = category.name;
                    this.isSearching = false;
                    this.open = false;
                    window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: category.id, categoryName: category.name } }));
                },

                clear() {
                    this.selectedId = null; this.selectedName = ''; this.search = '';
                    this.isSearching = false; this.open = true;
                    window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: null, categoryName: '' } }));
                },

                selectFirst() {
                    const filtered = this.filteredChildren();
                    if (filtered.length > 0) this.select(filtered[0]);
                },

                filteredChildren() {
                    const allChildren = this.allCategoriesSorted;
                    if (!this.search || !this.isSearching) return allChildren;
                    const searchLower = this.search.toLowerCase();
                    return allChildren.filter(child => child.name.toLowerCase().includes(searchLower));
                },

                init() {
                    if (this.selectedId) {
                        const found = this.allCategoriesSorted.find(c => c.id === this.selectedId);
                        if (found) {
                            this.selectedName = found.name;
                            this.search = found.name;
                            window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: found.id, categoryName: found.name } }));
                        }
                    }
                }
            }
        }
    </script>
</x-app-layout>
