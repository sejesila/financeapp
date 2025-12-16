@extends('layout')

@section('content')
    <div class="max-w-2xl mx-auto">

        <div class="flex items-center justify-between mb-6">
            <h1 class="text-3xl font-bold">Top-Up Account</h1>
            <a href="{{ route('accounts.show', $account) }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Account
            </a>
        </div>

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

        <!-- Info Box -->
        <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-6">
            <p class="text-sm text-blue-800">
                <strong>Note:</strong> If you're receiving a loan (M-Shwari, KCB M-Pesa, Fuliza, etc.), it's better to use the
                <a href="{{ route('loans.create') }}?account_id={{ $account->id }}" class="underline font-semibold">
                    Create Loan
                </a> form to properly track repayments and interest.
            </p>
        </div>

        <!-- Account Type Info -->
        @if($account->type === 'bank')
            <div class="bg-purple-50 border-l-4 border-purple-500 p-4 mb-6">
                <p class="text-sm text-purple-800">
                    <strong>Bank Account:</strong> Only salary income can be recorded for this account type.
                    Other income types should be recorded on cash accounts.
                </p>
            </div>
        @elseif($account->type === 'airtel_money')
            <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
                <p class="text-sm text-red-800">
                    <strong>Airtel Money Account:</strong> Only income transactions can be recorded.
                    Loans should be recorded on your M-Pesa account.
                </p>
            </div>
        @elseif($account->type === 'mpesa')
            <div class="bg-orange-50 border-l-4 border-orange-500 p-4 mb-6">
                <p class="text-sm text-orange-800">
                    <strong>M-Pesa Account:</strong> Salary income should be recorded on your bank account.
                    Other income types and liabilities can be recorded here.
                </p>
            </div>
        @endif

        <form method="POST" action="{{ route('accounts.topup.store', $account) }}" class="bg-white shadow rounded-lg p-6" id="topupForm">
            @csrf

            <div class="mb-4">
                <label for="category_id" class="block text-gray-700 font-semibold mb-2">
                    Source Category <span class="text-red-500">*</span>
                </label>
                <select name="category_id" id="category_id" class="w-full border border-gray-300 rounded px-4 py-2" required>
                    <option value="">-- Select Source --</option>

                    @php
                        $incomeCategories = $categories->filter(fn($cat) => $cat->type === 'income');
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
                                    {{ $cat->name }}
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
                    value="{{ old('amount') }}"
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
                <input
                    type="date"
                    name="date"
                    id="date"
                    value="{{ old('date', date('Y-m-d')) }}"
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
                    class="w-full border border-gray-300 rounded px-4 py-2"
                    placeholder="Any notes about this top-up"
                >{{ old('description') }}</textarea>
                @error('description')
                <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex gap-4">
                <button type="submit" class="bg-green-600 text-white px-6 py-2 rounded hover:bg-green-700">
                    Top-Up Account
                </button>
                <a href="{{ route('accounts.show', $account) }}" class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400">
                    Cancel
                </a>
            </div>
        </form>
    </div>

    <script>
        const categorySelect = document.getElementById('category_id');
        const topupForm = document.getElementById('topupForm');

        // Detect loan type from category name
        function detectLoanType(categoryName) {
            const name = categoryName.toLowerCase();

            if (name.includes('kcb') || name.includes('kcb-mpesa') || name.includes('kcb mpesa')) {
                return 'kcb_mpesa';
            }

            if (name.includes('mshwari') || name.includes('m-shwari')) {
                return 'mshwari';
            }

            // Default to mshwari for other loan types
            return 'mshwari';
        }

        // Check on form submit if liability category is selected
        topupForm.addEventListener('submit', function(e) {
            const selectedOption = categorySelect.options[categorySelect.selectedIndex];
            const categoryType = selectedOption.getAttribute('data-type');

            if (categoryType === 'liability') {
                e.preventDefault();

                const amount = document.getElementById('amount').value;
                const date = document.getElementById('date').value;
                const description = document.getElementById('description').value;
                const categoryName = selectedOption.text;

                // Detect loan type
                const loanType = detectLoanType(categoryName);

                // Build URL with query parameters
                const url = new URL('{{ route("loans.create") }}', window.location.origin);
                url.searchParams.append('account_id', '{{ $account->id }}');
                if (amount) url.searchParams.append('amount', amount);
                if (date) url.searchParams.append('date', date);
                url.searchParams.append('source', categoryName);
                url.searchParams.append('loan_type', loanType);
                if (description) url.searchParams.append('notes', description);

                window.location.href = url.toString();
            }
        });
    </script>
@endsection
