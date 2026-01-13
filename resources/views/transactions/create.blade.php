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
                        placeholder="Enter amount"
                        class="w-full text-sm sm:text-base border dark:border-gray-600 p-2 sm:p-2.5 rounded focus:outline-none focus:border-indigo-500 dark:bg-gray-700 dark:text-gray-200"
                        required
                    >
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
        (function() {
            // Generate unique idempotency key when page loads
            const idempotencyKey = `txn_${Date.now()}_${Math.random().toString(36).substring(2, 15)}`;

            document.addEventListener('DOMContentLoaded', function() {
                const form = document.querySelector('form[action*="transactions"]');

                if (!form) {
                    console.warn('Transaction form not found');
                    return;
                }

                // Create and add hidden input for idempotency key
                const idempotencyInput = document.createElement('input');
                idempotencyInput.type = 'hidden';
                idempotencyInput.name = 'idempotency_key';
                idempotencyInput.value = idempotencyKey;
                form.appendChild(idempotencyInput);

                console.log('Idempotency key generated:', idempotencyKey);

                // Disable submit button after first click
                const submitButton = form.querySelector('button[type="submit"]');
                let isSubmitting = false;

                if (submitButton) {
                    const originalButtonText = submitButton.textContent;

                    form.addEventListener('submit', function(e) {
                        if (isSubmitting) {
                            e.preventDefault();
                            console.log('Form already submitting, prevented duplicate submission');
                            return false;
                        }

                        isSubmitting = true;
                        submitButton.disabled = true;
                        submitButton.innerHTML = '<span class="inline-flex items-center"><svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Processing...</span>';

                        // Store submission state
                        sessionStorage.setItem('transaction_submitting', 'true');
                        sessionStorage.setItem('transaction_key', idempotencyKey);

                        // Re-enable after 10 seconds in case of network error
                        setTimeout(() => {
                            isSubmitting = false;
                            submitButton.disabled = false;
                            submitButton.textContent = originalButtonText;
                            sessionStorage.removeItem('transaction_submitting');
                            console.log('Submit button re-enabled after timeout');
                        }, 10000);
                    });
                }
            });

            // Check on page load if we were in middle of submission
            window.addEventListener('load', function() {
                const wasSubmitting = sessionStorage.getItem('transaction_submitting');
                const lastKey = sessionStorage.getItem('transaction_key');

                if (wasSubmitting === 'true') {
                    // Clear the flags
                    sessionStorage.removeItem('transaction_submitting');
                    sessionStorage.removeItem('transaction_key');

                    // Show warning message
                    const warningDiv = document.createElement('div');
                    warningDiv.className = 'bg-yellow-100 border-l-4 border-yellow-500 text-yellow-700 p-4 rounded mb-4';
                    warningDiv.innerHTML = `
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-500" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm">
                                    Your previous transaction may have been recorded. Please check your transaction history before submitting again.
                                </p>
                            </div>
                        </div>
                    `;

                    const container = document.querySelector('.max-w-2xl');
                    if (container) {
                        container.insertBefore(warningDiv, container.firstChild);
                    }

                    console.log('Detected interrupted submission. Last key:', lastKey);
                }
            });
        })();
    </script>

    <script>
        function transactionForm() {
            return {
                amount: {{ old('amount') ?: 'null' }},
                accountId: '{{ old('account_id', $mpesaAccount->id ?? '') }}',
                accountType: '',
                mobileMoneyType: '{{ old('mobile_money_type', 'send_money') }}',
                showTransactionTypeSelector: false,

                init() {
                    this.$nextTick(() => {
                        this.onAccountChange();
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

                    // Show transaction type selector only for mobile money accounts
                    if (this.accountType === 'mpesa' || this.accountType === 'airtel_money') {
                        this.showTransactionTypeSelector = true;

                        // Airtel Money does not support Pochi
                        if (this.accountType === 'airtel_money' && this.mobileMoneyType === 'pochi_la_biashara') {
                            this.mobileMoneyType = 'send_money';
                        }
                    } else {
                        this.showTransactionTypeSelector = false;
                        this.mobileMoneyType = 'send_money';
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
                allCategoriesSorted: allCategoriesArray, // Pre-sorted flat list from backend

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
                    // Use the pre-sorted flat list from backend
                    const allChildren = this.allCategoriesSorted;

                    // If not searching, return all children (already sorted by usage_count DESC)
                    if (!this.search || !this.isSearching) {
                        return allChildren;
                    }

                    // Filter by search term (maintains usage_count order)
                    const searchLower = this.search.toLowerCase();
                    return allChildren.filter(child =>
                        child.name.toLowerCase().includes(searchLower)
                    );
                },

                init() {
                    if (this.selectedId) {
                        // Find in the sorted list
                        const found = this.allCategoriesSorted.find(c => c.id === this.selectedId);
                        if (found) {
                            this.selectedName = found.name;
                            this.search = found.name;

                            // Notify transaction form
                            window.dispatchEvent(new CustomEvent('category-selected', {
                                detail: {
                                    categoryId: found.id,
                                    categoryName: found.name
                                }
                            }));
                        }
                    }
                }
            }
        }
    </script>

</x-app-layout>
