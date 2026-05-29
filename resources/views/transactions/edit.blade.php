<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 sm:gap-4">
            <h2 class="font-semibold text-base sm:text-lg md:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Transaction') }}
            </h2>
            <a onclick="window.history.length > 1 ? history.back() : window.location='{{ route('transactions.index') }}'"
               class="text-sm sm:text-base text-indigo-600 hover:text-indigo-800 cursor-pointer">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto mt-4 sm:mt-10 p-4 sm:p-6 bg-white dark:bg-gray-800 shadow-xl rounded-xl sm:rounded-2xl">

        @if($errors->any())
            <div class="bg-red-100 text-red-700 p-3 sm:p-4 rounded text-sm mb-4 sm:mb-6">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-3 sm:p-4 rounded text-sm mb-4 sm:mb-6">{{ session('error') }}</div>
        @endif

        <form action="{{ route('transactions.update', $transaction) }}" method="POST" class="space-y-4 sm:space-y-5">
            @csrf
            @method('PUT')

            <div x-data="transactionForm()">
                <!-- Date -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Date</label>
                    <input type="datetime-local" name="date" value="{{ old('date', $transaction->date->format('Y-m-d\TH:i')) }}"
                           class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200" required>
                </div>

                <!-- Description -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Description</label>
                    <input type="text" name="description" value="{{ old('description', $transaction->description) }}"
                           placeholder="e.g. Supermarket shopping"
                           class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200" required>
                </div>

                <!-- Category -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Category</label>
                    <div
                        x-data='categoryDropdown(@json($categoryGroups), @json($allCategoriesArray), @json(old("category_id", $transaction->category_id)))'
                        x-init="init()" class="relative w-full" @click.outside="closeDropdown()" x-id="['category-dropdown']">
                        <div class="relative">
                            <input type="text" x-model="search" @input="handleInput()" @focus="open = true"
                                   @keydown.escape="closeDropdown()" @keydown.enter.prevent="selectFirst()"
                                   placeholder="Search or select a category..."
                                   class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 pr-16 sm:pr-20 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                                   :aria-owns="$id('category-dropdown')" :aria-expanded="open" aria-label="Category" autocomplete="off">
                            <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                                <button type="button" x-show="selectedId" @click.stop="clear()"
                                        class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                                <button type="button" @click.stop="toggle()" class="p-1 hover:bg-gray-200 dark:hover:bg-gray-600 rounded transition">
                                    <svg class="w-4 h-4 text-gray-500 dark:text-gray-400 transition-transform" :class="{ 'rotate-180': open }"
                                         fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
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
                    <input type="number" step="0.01" name="amount"
                           value="{{ old('amount', $transaction->amount) }}"
                           placeholder="Enter amount"
                           class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                           required>
                </div>

                <!-- Account -->
                <div class="mb-4 sm:mb-5">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Account</label>
                    <select name="account_id" x-model="accountId" @change="onAccountChange()"
                            class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                            required>
                        <option value="">-- Select Account --</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->id }}" data-type="{{ $account->type }}"
                                {{ old('account_id', $transaction->account_id) == $account->id ? 'selected' : '' }}>
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

                <!-- Transaction Type (for mobile money) -->
                <div class="mb-4 sm:mb-5" x-show="showTransactionTypeSelector">
                    <label class="block text-sm sm:text-base font-semibold mb-1 dark:text-gray-200">Transaction Type</label>
                    <select name="mobile_money_type"
                            x-ref="mobileMoneySelect"
                            class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200">
                        <template x-for="type in transactionTypeOptions" :key="type.key">
                            <option :value="type.key" x-text="type.label" :selected="type.key === mobileMoneyType"></option>
                        </template>
                    </select>
                </div>

                <!-- Submit -->
                <div class="flex flex-col-reverse sm:flex-row items-stretch sm:items-center justify-between gap-3 pt-2">
                    <a href="{{ route('transactions.show', $transaction) }}"
                       class="text-center sm:text-left text-sm sm:text-base text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 py-2 sm:py-0">
                        Cancel
                    </a>
                    <button type="submit"
                            class="w-full sm:w-auto bg-indigo-600 text-white px-6 py-2.5 sm:py-2 rounded-lg hover:bg-indigo-700 transition text-sm sm:text-base font-medium">
                        Update Transaction
                    </button>
                </div>
            </div>
        </form>
    </div>

    <script>
        function transactionForm() {
            return {
                accountId: '{{ old('account_id', $transaction->account_id) }}',
                accountType: '',
                mobileMoneyType: '{{ old('mobile_money_type', $transaction->mobile_money_type ?? '') }}',
                showTransactionTypeSelector: false,
                defaultMpesaType: '{{ $defaultMpesaType ?? 'send_money' }}',
                defaultAirtelType: '{{ $defaultAirtelType ?? 'send_money' }}',
                transactionTypeOptions: [],
                mpesaTypes: @json($mpesaTransactionTypes),
                airtelTypes: @json($airtelTransactionTypes),
                // Use the transaction's actual mobile money type
                originalMobileMoneyType: '{{ $transaction->mobile_money_type ?? '' }}',

                init() {
                    console.log('Original mobile money type:', this.originalMobileMoneyType);
                    console.log('Current mobileMoneyType:', this.mobileMoneyType);
                    this.$nextTick(() => {
                        this.onAccountChange();
                        console.log('After onAccountChange - mobileMoneyType:', this.mobileMoneyType);
                    });
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
                        this.transactionTypeOptions = this.accountType === 'mpesa'
                            ? this.mpesaTypes
                            : this.airtelTypes;

                        if (this.originalMobileMoneyType) {
                            const typeExists = this.transactionTypeOptions.some(t => t.key === this.originalMobileMoneyType);
                            this.mobileMoneyType = typeExists
                                ? this.originalMobileMoneyType
                                : (this.transactionTypeOptions[0]?.key ?? '');
                        } else if (!this.mobileMoneyType) {
                            this.mobileMoneyType = this.accountType === 'mpesa'
                                ? this.defaultMpesaType
                                : this.defaultAirtelType;
                        }

                        this.showTransactionTypeSelector = true;
                    } else {
                        this.showTransactionTypeSelector = false;
                        this.mobileMoneyType = '';
                        this.transactionTypeOptions = [];
                    }
                }
            }
        }

        // Your existing categoryDropdown function remains exactly the same
        function categoryDropdown(categoryGroups, allCategoriesArray, oldId = null) {
            return {
                open: false, search: '', selectedId: oldId, selectedName: '', isSearching: false,
                categories: categoryGroups, allCategoriesSorted: allCategoriesArray,
                toggle() { this.open = !this.open; if (this.open) { this.isSearching = false; this.search = ''; } },
                closeDropdown() { this.open = false; if (!this.isSearching && this.selectedName) this.search = this.selectedName; },
                handleInput() { this.isSearching = true; this.open = true; },
                select(category) {
                    this.selectedId = category.id; this.selectedName = category.name;
                    this.search = category.name; this.isSearching = false; this.open = false;
                    window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: category.id, categoryName: category.name } }));
                },
                clear() {
                    this.selectedId = null; this.selectedName = ''; this.search = ''; this.isSearching = false; this.open = true;
                    window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: null, categoryName: '' } }));
                },
                selectFirst() { const f = this.filteredChildren(); if (f.length > 0) this.select(f[0]); },
                filteredChildren() {
                    const all = this.allCategoriesSorted;
                    if (!this.search || !this.isSearching) return all;
                    return all.filter(c => c.name.toLowerCase().includes(this.search.toLowerCase()));
                },
                init() {
                    if (this.selectedId) {
                        const found = this.allCategoriesSorted.find(c => c.id === this.selectedId);
                        if (found) {
                            this.selectedName = found.name; this.search = found.name;
                            window.dispatchEvent(new CustomEvent('category-selected', { detail: { categoryId: found.id, categoryName: found.name } }));
                        }
                    }
                }
            }
        }
    </script>
</x-app-layout>
