<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    @if ($errors->any())
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800">Validation Errors</h3>
                                    <div class="mt-2 text-sm text-red-700">
                                        <ul class="list-disc pl-5 space-y-1">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 bg-red-50 border-l-4 border-red-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-red-700">{{ session('error') }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-2xl font-semibold text-gray-800">Record New Loan</h2>
                        <a href="{{ route('loans-given.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                    </div>

                    <form method="POST" action="{{ route('loans-given.store') }}" class="space-y-6">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Borrower Information -->
                            <div>
                                <label for="borrower_name" class="block text-sm font-medium text-gray-700">Borrower Name <span class="text-red-600">*</span></label>
                                <input type="text" id="borrower_name" name="borrower_name" value="{{ old('borrower_name') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('borrower_name') border-red-500 @enderror" required>
                                @error('borrower_name')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Loan Details -->
                            <div>
                                <label for="account_id" class="block text-sm font-medium text-gray-700">Account <span class="text-red-600">*</span></label>
                                <select id="account_id" name="account_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('account_id') border-red-500 @enderror" required>
                                    <option value="">Select Account</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->id }}"
                                                data-balance="{{ $account->current_balance }}"
                                            {{ old('account_id') == $account->id ? 'selected' : '' }}>
                                            {{ $account->name }} (KES {{ number_format($account->current_balance, 0) }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500" id="account_balance_hint">Funds are deducted from this account on disbursement.</p>
                                @error('account_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="principal_amount" class="block text-sm font-medium text-gray-700">Principal Amount (KES) <span class="text-red-600">*</span></label>
                                <input type="number" step="0.01" min="1" id="principal_amount" name="principal_amount" value="{{ old('principal_amount') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('principal_amount') border-red-500 @enderror" required>
                                @error('principal_amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <!-- Referrer -->
                            <div>
                                <label for="referrer_id" class="block text-sm font-medium text-gray-700">Referred By</label>
                                <select id="referrer_id" name="referrer_id"
                                        class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('referrer_id') border-red-500 @enderror">
                                    <option value="">Direct (no referrer)</option>
                                    @foreach($referrers as $referrer)
                                        <option value="{{ $referrer->id }}"
                                                data-default-share="{{ $referrer->default_share_percentage }}"
                                            {{ old('referrer_id') == $referrer->id ? 'selected' : '' }}>
                                            {{ $referrer->name }} ({{ $referrer->default_share_percentage }}% default)
                                        </option>
                                    @endforeach
                                </select>
                                @error('referrer_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Referrer share % — only shown/required when a referrer is picked -->
                            <div id="referrer_share_wrapper" style="display: none;">
                                <label for="referrer_share_percentage" class="block text-sm font-medium text-gray-700">Referrer's Share (%)</label>
                                <input type="number" step="0.01" min="0" max="100" id="referrer_share_percentage" name="referrer_share_percentage"
                                       value="{{ old('referrer_share_percentage') }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('referrer_share_percentage') border-red-500 @enderror">
                                <p class="mt-1 text-xs text-gray-500">Defaults to the referrer's usual %, but you can override it for this loan.</p>
                                @error('referrer_share_percentage')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <!-- Dates -->
                            <div>
                                <label for="disbursed_date" class="block text-sm font-medium text-gray-700">Disbursement Date <span class="text-red-600">*</span></label>
                                <input type="date" id="disbursed_date" name="disbursed_date" value="{{ old('disbursed_date', now()->format('Y-m-d')) }}"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('disbursed_date') border-red-500 @enderror" required>
                                @error('disbursed_date')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                                <p class="mt-1 text-xs text-gray-500">Due date is automatically set to 30 days from this date.</p>
                            </div>
                        </div>

                        <!-- Notes -->
                        <div>
                            <label for="notes" class="block text-sm font-medium text-gray-700">Notes</label>
                            <textarea id="notes" name="notes" rows="3"
                                      class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 @error('notes') border-red-500 @enderror">{{ old('notes') }}</textarea>
                            @error('notes')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Summary Alert -->
                        <div class="bg-blue-50 border-l-4 border-blue-400 p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-blue-700">
                                        <span class="font-medium">No interest rate needed here.</span>
                                        Just record what you're lending out. When repayments come in and you
                                        close the loan out, the interest and rate are calculated automatically
                                        from whatever total amount you actually received.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-4">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Record Loan
                            </button>
                            <a href="{{ route('loans-given.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 focus:bg-gray-700 active:bg-gray-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const accountSelect = document.getElementById('account_id');
            const principalInput = document.getElementById('principal_amount');
            const hint = document.getElementById('account_balance_hint');

            function checkBalance() {
                const opt = accountSelect.options[accountSelect.selectedIndex];
                if (!opt || !opt.value) return;
                const balance = parseFloat(opt.getAttribute('data-balance')) || 0;
                const amount = parseFloat(principalInput.value) || 0;

                if (amount > balance) {
                    hint.textContent = `⚠️ Amount exceeds available balance (KES ${balance.toLocaleString('en-US', {maximumFractionDigits: 0})})`;
                    hint.className = 'mt-1 text-xs text-red-600 font-medium';
                } else {
                    hint.textContent = 'Funds are deducted from this account on disbursement.';
                    hint.className = 'mt-1 text-xs text-gray-500';
                }
            }

            accountSelect.addEventListener('change', checkBalance);
            principalInput.addEventListener('input', checkBalance);

            // Referrer share toggle
            const referrerSelect = document.getElementById('referrer_id');
            const shareWrapper = document.getElementById('referrer_share_wrapper');
            const shareInput = document.getElementById('referrer_share_percentage');

            function toggleReferrerShare() {
                const opt = referrerSelect.options[referrerSelect.selectedIndex];
                if (opt && opt.value) {
                    shareWrapper.style.display = '';
                    if (!shareInput.value) {
                        shareInput.value = opt.getAttribute('data-default-share') || '';
                    }
                } else {
                    shareWrapper.style.display = 'none';
                    shareInput.value = '';
                }
            }

            referrerSelect.addEventListener('change', toggleReferrerShare);
            toggleReferrerShare(); // run once on load, in case old('referrer_id') is set after a validation error
        });
    </script>
</x-app-layout>
