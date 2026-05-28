<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Top-Up Account') }}
            </h2>
            <a href="{{ route('accounts.show',$account) }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Account
            </a>
        </div>
    </x-slot>

    <div class="max-w-2xl mx-auto">

        @if(session('success'))
            <div class="bg-green-100 text-green-700 p-4 rounded mb-6">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Sacco Dividends availability banner --}}
        @if($showSaccoDividends)
            <div class="bg-blue-50 border border-blue-200 text-blue-800 p-4 rounded mb-6 flex items-start gap-3">
                <div>
                    <p class="font-semibold">Sacco Dividends available</p>
                    <p class="text-sm mt-1">
                        You can record your Sacco Dividends this month (10 Apr – 10 May).
                        This option disappears once recorded or after 10 May.
                    </p>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('accounts.topup.store', $account) }}" class="bg-white shadow rounded-lg p-6" id="topupForm" x-data="topupForm()">
            @csrf

            <div class="mb-4">
                <label for="category_id" class="block text-gray-700 font-semibold mb-2">
                    Source Category
                    <span class="text-red-500">*</span>
                    <span id="category-optional-note" class="text-xs text-gray-500 font-normal italic" style="display: none;">
                        (optional for client funds)
                    </span>
                </label>
                <select name="category_id" id="category_id" class="w-full border border-gray-300 rounded px-4 py-2" @change="updateCategoryRequired()">
                    <option value="">-- Select Source --</option>

                    @php
                        $incomeCategories    = $categories->filter(fn($cat) => $cat->type === 'income');
                        $liabilityCategories = $categories->filter(fn($cat) => $cat->type === 'liability');
                    @endphp

                    @if($incomeCategories->count() > 0)
                        <optgroup label="Income">
                            @foreach($incomeCategories as $cat)
                                <option
                                    value="{{ $cat->id }}"
                                    data-type="{{ $cat->type }}"
                                    {{ old('category_id') == $cat->id ? 'selected' : '' }}
                                >
                                    {{ $cat->name === 'Sacco Dividends' ? '🏦 ' . $cat->name : $cat->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif

                    @if($liabilityCategories->count() > 0)
                        <optgroup label="Liability (Loans/Debt)">
                            @foreach($liabilityCategories as $cat)
                                <option
                                    value="{{ $cat->id }}"
                                    data-type="{{ $cat->type }}"
                                    data-loan-type="{{ strtolower($cat->name) }}"
                                    {{ old('category_id') == $cat->id ? 'selected' : '' }}
                                >
                                    {{ $cat->name }}
                                </option>
                            @endforeach
                        </optgroup>
                    @endif
                </select>
                @error('category_id')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="amount" class="block text-gray-700 font-semibold mb-2">
                    Amount <span class="text-red-500">*</span>
                </label>
                <input
                    type="number"
                    name="amount"
                    id="amount"
                    step="0.01"
                    x-model="amount"
                    value="{{ old('amount') }}"
                    placeholder="Enter amount"
                    class="w-full border border-gray-300 rounded px-4 py-2"
                    required
                >
                @error('amount')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="date" class="block text-gray-700 font-semibold mb-2">
                    Date <span class="text-red-500">*</span>
                </label>
                <input type="datetime-local" name="date" id="date" x-model="date"
                       value="{{ old('date', now()->format('Y-m-d\TH:i')) }}"
                    class="w-full border border-gray-300 rounded px-4 py-2"
                    required
                >
                @error('date')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-6">
                <label for="description" class="block text-gray-700 font-semibold mb-2">
                    Description (Optional)
                </label>
                <textarea
                    name="description"
                    id="description"
                    rows="3"
                    x-model="description"
                    class="w-full border border-gray-300 rounded px-4 py-2"
                    placeholder="Any notes about this top-up"
                >{{ old('description') }}</textarea>
                @error('description')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            @if($isSavings)
                <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox"
                               name="is_client_fund"
                               id="is_client_fund"
                               value="1"
                               x-model="isClientFund"
                               @change="updateCategoryRequired()"
                               class="mt-0.5 rounded border-gray-300 text-indigo-600">
                        <div>
                            <span class="font-semibold text-amber-800 text-sm">Record as Client Fund</span>
                            <p class="text-xs text-amber-700 mt-0.5">
                                Check this if the deposit belongs to a client and needs to be tracked separately.
                            </p>
                        </div>
                    </label>
                </div>
            @endif

            <div class="flex gap-4">
                <button type="submit" id="submit-btn"
                        class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                    Top-Up Account
                </button>
                <a href="{{ route('accounts.show', $account) }}"
                   class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        function topupForm() {
            return {
                amount: '{{ old('amount') }}',
                date: '{{ old('date', now()->format('Y-m-d\TH:i')) }}',
                description: '{{ old('description') }}',
                isClientFund: @if($isSavings) {{ old('is_client_fund') ? 'true' : 'false' }} @else false @endif,

                init() {
                    this.updateCategoryRequired();
                    this.$el.addEventListener('submit', (e) => this.handleSubmit(e));
                },

                updateCategoryRequired() {
                    const categorySelect = document.getElementById('category_id');
                    const optionalNote = document.getElementById('category-optional-note');

                    if (this.isClientFund) {
                        // Remove required attribute - user can proceed without selecting category
                        categorySelect.removeAttribute('required');
                        optionalNote.style.display = 'inline';
                        document.getElementById('submit-btn').textContent = 'Continue to Client Fund →';
                    } else {
                        // Restore required attribute - must select category
                        categorySelect.setAttribute('required', 'required');
                        optionalNote.style.display = 'none';
                        document.getElementById('submit-btn').textContent = 'Top-Up Account';
                    }
                },

                handleSubmit(e) {
                    // If recording as client fund, redirect without needing category
                    if (this.isClientFund) {
                        e.preventDefault();
                        const url = new URL('{{ route("client-funds.create") }}', window.location.origin);
                        url.searchParams.append('account_id', '{{ $account->id }}');
                        if (this.amount) url.searchParams.append('amount', this.amount);
                        if (this.date) url.searchParams.append('date', this.date);
                        if (this.description) url.searchParams.append('purpose', this.description);
                        window.location.href = url.toString();
                        return;
                    }

                    // Otherwise, validate category is selected
                    const categorySelect = document.getElementById('category_id');
                    if (!categorySelect.value) {
                        e.preventDefault();
                        categorySelect.focus();
                        categorySelect.style.borderColor = '#ef4444';
                        return;
                    }

                    const selectedOption = categorySelect.options[categorySelect.selectedIndex];
                    const categoryType = selectedOption.getAttribute('data-type');

                    // If it's a loan, redirect to loan form
                    if (categoryType === 'liability') {
                        e.preventDefault();

                        const categoryName = selectedOption.text.replace('🏦 ', '').trim();
                        const loanType = this.detectLoanType(categoryName);

                        const url = new URL('{{ route("loans.create") }}', window.location.origin);
                        url.searchParams.append('account_id', '{{ $account->id }}');
                        if (this.amount) url.searchParams.append('amount', this.amount);
                        if (this.date) url.searchParams.append('date', this.date);
                        url.searchParams.append('source', categoryName);
                        url.searchParams.append('loan_type', loanType);
                        if (this.description) url.searchParams.append('notes', this.description);

                        window.location.href = url.toString();
                    }
                },

                detectLoanType(categoryName) {
                    const name = categoryName.toLowerCase();

                    if (name.includes('kcb') || name.includes('kcb-mpesa') || name.includes('kcb mpesa')) {
                        return 'kcb_mpesa';
                    }
                    if (name.includes('mshwari') || name.includes('m-shwari')) {
                        return 'mshwari';
                    }

                    return 'other';
                }
            }
        }
    </script>
</x-app-layout>
