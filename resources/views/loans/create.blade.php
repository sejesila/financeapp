<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Record New Loan') }}
            </h2>
            <a href="{{ route('loans.index') }}" class="text-indigo-600 hover:text-indigo-800">
                ← Back to Loans
            </a>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4">
        @if(session('error'))
            <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
                {{ session('error') }}
            </div>
        @endif

        {{-- Show info banner if redirected from top-up --}}
        @if($fromTopup)
            <div class="bg-blue-100 border-l-4 border-blue-500 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd"
                                  d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z"
                                  clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-blue-800">
                            <strong>Redirected from Account Top-Up:</strong> The loan source, receiving account, and
                            amount have been pre-filled and cannot be changed. You can still modify the due date and
                            notes.
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Form Section -->
            <div class="lg:col-span-2">
                <form method="POST" action="{{ route('loans.store') }}" class="bg-white shadow rounded-lg p-6">
                    @csrf

                    {{-- Hidden fields to track if from top-up --}}
                    @if($fromTopup)
                        <input type="hidden" name="from_topup" value="1">
                        <input type="hidden" name="original_source" value="{{ $prefillData['source'] }}">
                        <input type="hidden" name="original_account_id" value="{{ $prefillData['account_id'] }}">
                        <input type="hidden" name="original_amount" value="{{ $prefillData['amount'] }}">
                    @endif

                    <!-- Loan Type Selection -->
                    <div class="mb-4">
                        <label for="loan_type" class="block text-gray-700 font-semibold mb-2">
                            Loan Type <span class="text-red-500">*</span>
                            @if($fromTopup)
                                <span class="text-xs text-gray-500 font-normal">(Locked from top-up)</span>
                            @endif
                        </label>
                        <select
                            name="loan_type"
                            id="loan_type"
                            class="w-full border border-gray-300 rounded px-4 py-2 @error('loan_type') border-red-500 @enderror @if($fromTopup) bg-gray-100 cursor-not-allowed @endif"
                            @if($fromTopup) disabled @endif
                            required
                        >
                            <option value="mshwari" {{ $loanType === 'mshwari' ? 'selected' : '' }}>M-Shwari (7.5% facilitation fee, early repayment bonus)</option>
                            <option value="kcb_mpesa" {{ $loanType === 'kcb_mpesa' ? 'selected' : '' }}>KCB M-Pesa (1.76% facility fee + 7.05% interest)</option>
                            <option value="other" {{ $loanType === 'other' ? 'selected' : '' }}>Other (Custom loan from friends, family, etc.)</option>
                        </select>

                        {{-- Hidden input to pass loan_type when select is disabled --}}
                        @if($fromTopup)
                            <input type="hidden" name="loan_type" value="{{ $loanType }}">
                            <p class="text-xs text-gray-500 mt-1">
                                <svg class="inline w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                This field is locked because you came from the top-up form
                            </p>
                        @endif

                        @error('loan_type')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Loan Source -->
                    <div class="mb-4">
                        <label for="source" class="block text-gray-700 font-semibold mb-2">
                            Loan Source <span class="text-red-500">*</span>
                            @if($fromTopup)
                                <span class="text-xs text-gray-500 font-normal">(Locked from top-up)</span>
                            @endif
                        </label>
                        <input
                            type="text"
                            name="source"
                            id="source"
                            value="{{ $prefillData['source'] ?? old('source') }}"
                            placeholder="e.g., M-Shwari, KCB-Mpesa, Fuliza, Bank Loan"
                            class="w-full border border-gray-300 rounded px-4 py-2 @error('source') border-red-500 @enderror @if($fromTopup) bg-gray-100 cursor-not-allowed @endif"
                            @if($fromTopup) readonly @endif
                            required
                        >
                        @error('source')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @if($fromTopup)
                            <p class="text-xs text-gray-500 mt-1">
                                <svg class="inline w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                This field is locked because you came from the top-up form
                            </p>
                        @endif
                    </div>

                    <!-- Account Selection -->
                    <div class="mb-4">
                        <label for="account_id" class="block text-gray-700 font-semibold mb-2">
                            Receiving MPesa Account <span class="text-red-500">*</span>
                            @if($fromTopup)
                                <span class="text-xs text-gray-500 font-normal">(Locked from top-up)</span>
                            @endif
                        </label>
                        <select
                            name="account_id"
                            id="account_id"
                            class="w-full border border-gray-300 rounded px-4 py-2 @error('account_id') border-red-500 @enderror @if($fromTopup) bg-gray-100 cursor-not-allowed @endif"
                            @if($fromTopup) disabled @endif
                            required
                        >
                            <option value="">-- Select MPesa Account --</option>
                            @forelse($accounts as $account)
                                <option
                                    value="{{ $account->id }}"
                                    {{ ($prefillData['account_id'] ?? old('account_id')) == $account->id ? 'selected' : '' }}
                                >
                                    {{ $account->name }} (Balance: KES {{ number_format($account->current_balance, 0) }})
                                </option>
                            @empty
                                <option disabled>No active MPesa accounts available</option>
                            @endforelse
                        </select>
                        {{-- Hidden input to pass account_id when select is disabled --}}
                        @if($fromTopup)
                            <input type="hidden" name="account_id" value="{{ $prefillData['account_id'] }}">
                        @endif
                        @error('account_id')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @if($fromTopup)
                            <p class="text-xs text-gray-500 mt-1">
                                <svg class="inline w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                This field is locked because you came from the top-up form
                            </p>
                        @else
                            <p class="text-xs text-gray-500 mt-1">The loan amount will be deposited to this MPesa account</p>
                        @endif
                    </div>

                    <!-- Principal Amount -->
                    <div class="mb-6">
                        <label for="principal_amount" class="block text-gray-700 font-semibold mb-2">
                            Loan Amount (Principal) <span class="text-red-500">*</span>
                            @if($fromTopup)
                                <span class="text-xs text-gray-500 font-normal">(Locked from top-up)</span>
                            @endif
                        </label>
                        <div class="flex items-center">
                            <span class="text-gray-700 mr-2">KES</span>
                            <input
                                type="number"
                                name="principal_amount"
                                id="principal_amount"
                                value="{{ $prefillData['amount'] ?? old('principal_amount') }}"
                                step="0.01"
                                min="1"
                                class="flex-1 border border-gray-300 rounded px-4 py-2 @error('principal_amount') border-red-500 @enderror @if($fromTopup) bg-gray-100 cursor-not-allowed @endif"
                                placeholder="e.g., 1000"
                                @if($fromTopup) readonly @endif
                                required
                            >
                        </div>
                        @error('principal_amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                        @if($fromTopup)
                            <p class="text-xs text-gray-500 mt-1">
                                <svg class="inline w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                          d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z"
                                          clip-rule="evenodd"/>
                                </svg>
                                This field is locked because you came from the top-up form
                            </p>
                        @endif
                    </div>
                    <!-- Custom Interest Section (for "Other" loan type) -->
                    <div id="custom-rates-section" class="mb-6 hidden">
                        <label for="custom_interest_amount" class="block text-gray-700 font-semibold mb-2">
                            Interest Amount <span class="text-gray-500 text-sm font-normal">(Optional)</span>
                        </label>
                        <div class="flex items-center">
                            <span class="text-gray-700 mr-2">KES</span>
                            <input
                                type="number"
                                name="custom_interest_amount"
                                id="custom_interest_amount"
                                value="{{ old('custom_interest_amount', 0) }}"
                                step="0.01"
                                min="0"
                                class="flex-1 border border-gray-300 rounded px-4 py-2"
                                placeholder="e.g., 100"
                            >
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Enter any fixed interest amount for this custom loan</p>
                        @error('custom_interest_amount')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Dates -->
                    <div class="grid grid-cols-2 gap-4 mb-6">
                        <div>
                            <label for="disbursed_date" class="block text-gray-700 font-semibold mb-2">
                                Disbursement Date <span class="text-red-500">*</span>
                            </label>
                            <input
                                type="date"
                                name="disbursed_date"
                                id="disbursed_date"
                                value="{{ $prefillData['date'] ?? old('disbursed_date', date('Y-m-d')) }}"
                                class="w-full border border-gray-300 rounded px-4 py-2 @error('disbursed_date') border-red-500 @enderror"
                                required
                            >
                            @error('disbursed_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="due_date" class="block text-gray-700 font-semibold mb-2">
                                Due Date
                                <span class="text-gray-500 text-sm font-normal" id="due-date-label">
            (Auto: 30 days)
        </span>
                            </label>
                            <input
                                type="date"
                                name="due_date"
                                id="due_date"
                                value="{{ old('due_date') }}"
                                class="w-full border border-gray-300 rounded px-4 py-2 bg-gray-100 @error('due_date') border-red-500 @enderror"
                                readonly
                            >
                            <p class="text-xs text-gray-500 mt-1" id="due-date-help">
                                📅 Auto-calculated: 30 days from disbursement
                            </p>
                            @error('due_date')
                            <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-6">
                        <label for="notes" class="block text-gray-700 font-semibold mb-2">
                            Notes <span class="text-gray-500 text-sm font-normal">(Optional)</span>
                        </label>
                        <textarea
                            name="notes"
                            id="notes"
                            rows="3"
                            class="w-full border border-gray-300 rounded px-4 py-2"
                            placeholder="Any additional information about this loan"
                        >{{ $prefillData['notes'] ?? old('notes') }}</textarea>
                        @error('notes')
                        <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Submit Buttons -->
                    <div class="flex gap-4">
                        <button type="submit"
                                class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-semibold">
                            Record Loan
                        </button>
                        <a href="{{ $fromTopup && $prefillData['account_id'] ? route('accounts.show', $prefillData['account_id']) : route('loans.index') }}"
                           class="bg-gray-300 text-gray-700 px-6 py-2 rounded hover:bg-gray-400 font-semibold">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>

            <!-- Breakdown Summary (Sticky) -->
            <div class="lg:sticky lg:top-4 lg:h-fit">
                <!-- M-Shwari Breakdown -->
                <div id="breakdown-mshwari"
                     class="bg-gradient-to-br from-blue-50 to-indigo-50 border-2 border-indigo-200 rounded-lg p-6 hidden">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">M-Shwari Loan Breakdown</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between pb-3 border-b border-indigo-200">
                            <span class="text-gray-700">Loan Amount</span>
                            <span class="font-semibold text-gray-900">KES <span id="ms_principal">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b border-indigo-100">
                            <div>
                                <p class="text-gray-700">Excise Duty (1.5%)</p>
                                <p class="text-xs text-gray-500">Deducted upfront</p>
                            </div>
                            <span class="font-semibold text-red-600">-KES <span id="ms_excise">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-indigo-200 bg-green-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Deposited to MPesa</p>
                                <p class="text-xs text-gray-600">You receive</p>
                            </div>
                            <span class="font-bold text-green-600 text-base">+KES <span id="ms_deposit">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b border-indigo-100">
                            <div>
                                <p class="text-gray-700">Facilitation Fee (7.5%)</p>
                                <p class="text-xs text-gray-500">Of principal amount</p>
                            </div>
                            <span class="font-semibold text-orange-600">+KES <span id="ms_facilitation">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-indigo-300 bg-red-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Standard Repayment</p>
                                <p class="text-xs text-gray-600">30-day term</p>
                            </div>
                            <span class="font-bold text-red-600 text-base">KES <span id="ms_standard">0</span></span>
                        </div>

                        <div class="mt-4 pt-4 border-t-2 border-indigo-300">
                            <p class="text-xs font-semibold text-gray-600 mb-3 uppercase">Early Repayment (Within 10 Days)</p>

                            <div class="flex justify-between pb-2 text-xs">
                                <span class="text-gray-700">Facilitation Refund (24%)</span>
                                <span class="text-green-600 font-semibold">-KES <span id="ms_refund">0</span></span>
                            </div>

                            <div class="flex justify-between p-2 bg-green-50 rounded border border-green-200">
                                <span class="text-gray-700 font-semibold">Early Repayment Total</span>
                                <span class="font-bold text-green-600">KES <span id="ms_early">0</span></span>
                            </div>
                        </div>

                        <div class="mt-4 pt-4 border-t-2 border-indigo-300 space-y-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-600">Savings (Early)</span>
                                <span class="text-green-600 font-semibold">KES <span id="ms_savings">0</span></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- KCB M-Pesa Breakdown -->
                <div id="breakdown-kcb"
                     class="bg-gradient-to-br from-purple-50 to-pink-50 border-2 border-purple-200 rounded-lg p-6 hidden">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">KCB M-Pesa Loan Breakdown</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between pb-3 border-b border-purple-200">
                            <span class="text-gray-700">Loan Amount</span>
                            <span class="font-semibold text-gray-900">KES <span id="kcb_principal">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-purple-200 bg-green-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Deposited to MPesa</p>
                                <p class="text-xs text-gray-600">Full amount received</p>
                            </div>
                            <span class="font-bold text-green-600 text-base">+KES <span id="kcb_deposit">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b border-purple-100">
                            <div>
                                <p class="text-gray-700">Facility Fee (1.76%)</p>
                                <p class="text-xs text-gray-500">Of principal amount</p>
                            </div>
                            <span class="font-semibold text-orange-600">+KES <span id="kcb_facility">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b border-purple-100">
                            <div>
                                <p class="text-gray-700">Interest (7.05%)</p>
                                <p class="text-xs text-gray-500">Of principal amount</p>
                            </div>
                            <span class="font-semibold text-orange-600">+KES <span id="kcb_interest">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-purple-300 bg-red-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Total Repayment</p>
                                <p class="text-xs text-gray-600">30-day term</p>
                            </div>
                            <span class="font-bold text-red-600 text-base">KES <span id="kcb_total">0</span></span>
                        </div>

                        <div class="mt-4 pt-4 border-t-2 border-purple-300">
                            <p class="text-xs font-semibold text-gray-600 mb-3 uppercase">Early Repayment (Within 10 Days)</p>
                            <p class="text-xs text-gray-600 bg-purple-100 p-2 rounded">
                                💰 Repay within 10 days to get the full facility fee (KES <span id="kcb_facility_refund">0</span>) refunded!
                            </p>
                        </div>
                    </div>
                </div>
                <!-- Other/Custom Breakdown -->
                <div id="breakdown-other"
                     class="bg-gradient-to-br from-amber-50 to-orange-50 border-2 border-amber-200 rounded-lg p-6 hidden">
                    <h3 class="text-lg font-bold text-gray-900 mb-4">Custom Loan Breakdown</h3>

                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between pb-3 border-b border-amber-200">
                            <span class="text-gray-700">Loan Amount (Principal)</span>
                            <span class="font-semibold text-gray-900">KES <span id="other_principal">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-amber-200 bg-green-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Deposited to Account</p>
                                <p class="text-xs text-gray-600">Full amount received</p>
                            </div>
                            <span class="font-bold text-green-600 text-base">+KES <span id="other_deposit">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b border-amber-100">
                            <div>
                                <p class="text-gray-700">Interest Amount</p>
                                <p class="text-xs text-gray-500">Fixed interest (if any)</p>
                            </div>
                            <span class="font-semibold text-orange-600">+KES <span id="other_interest">0</span></span>
                        </div>

                        <div class="flex justify-between pb-3 border-b-2 border-amber-300 bg-red-50 p-3 rounded">
                            <div>
                                <p class="text-gray-700 font-semibold">Total Repayment</p>
                                <p class="text-xs text-gray-600">Principal + Interest</p>
                            </div>
                            <span class="font-bold text-red-600 text-base">KES <span id="other_total">0</span></span>
                        </div>

                        <div class="mt-4 pt-4 border-t-2 border-amber-300">
                            <p class="text-xs text-gray-600 bg-amber-100 p-2 rounded" id="other_note">
                                ℹ️ Simple loan structure - no facility fees or complex calculations
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div id="breakdown-empty" class="bg-gray-50 border-2 border-dashed border-gray-300 rounded-lg p-6">
                    <p class="text-sm text-gray-500 text-center">
                        Enter loan amount to see breakdown
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- JavaScript Section - Place at the bottom before closing </x-app-layout> --}}
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const principalInput = document.getElementById('principal_amount');
            const loanTypeSelect = document.getElementById('loan_type');
            const disbursedDateInput = document.getElementById('disbursed_date');
            const dueDateInput = document.getElementById('due_date');
            const customRatesSection = document.getElementById('custom-rates-section');
            const customInterestInput = document.getElementById('custom_interest_amount');
            const breakdownMshwari = document.getElementById('breakdown-mshwari');
            const breakdownKcb = document.getElementById('breakdown-kcb');
            const breakdownOther = document.getElementById('breakdown-other');
            const breakdownEmpty = document.getElementById('breakdown-empty');

            // Toggle custom interest section
            function toggleCustomRates() {
                const loanType = loanTypeSelect.value;
                const dueDateLabel = document.getElementById('due-date-label');
                const dueDateHelp = document.getElementById('due-date-help');

                if (loanType === 'other') {
                    customRatesSection.classList.remove('hidden');
                    dueDateInput.removeAttribute('readonly');
                    dueDateInput.classList.remove('bg-gray-100');
                    dueDateLabel.textContent = '(Optional)';
                    dueDateHelp.textContent = '📅 Optional: Set your own repayment date';
                } else {
                    customRatesSection.classList.add('hidden');
                    dueDateInput.setAttribute('readonly', 'readonly');
                    dueDateInput.classList.add('bg-gray-100');
                    dueDateLabel.textContent = '(Auto: 30 days)';
                    dueDateHelp.textContent = '📅 Auto-calculated: 30 days from disbursement';
                }
            }

            // Auto-calculate due date (30 days from disbursement) - only for standard loans
            function updateDueDate() {
                const disbursedDate = disbursedDateInput.value;
                const loanType = loanTypeSelect.value;

                if (disbursedDate && loanType !== 'other') {
                    const date = new Date(disbursedDate);
                    date.setDate(date.getDate() + 30);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    dueDateInput.value = `${year}-${month}-${day}`;
                }
            }

            // M-Shwari breakdown calculator
            function calculateMshwariBreakdown() {
                const principal = parseFloat(principalInput.value) || 0;

                if (principal <= 0) {
                    return null;
                }

                const exciseDutyRate = 0.015;
                const facilitationFeeRate = 0.075;
                const facilitationRefundRate = 0.24;

                const facilitationFee = principal * facilitationFeeRate;
                const exciseDuty = principal * exciseDutyRate;
                const depositAmount = principal - exciseDuty;
                const standardRepayment = principal + facilitationFee;
                const earlyRepaymentDiscount = facilitationFee * facilitationRefundRate;
                const earlyRepayment = standardRepayment - earlyRepaymentDiscount;
                const savings = standardRepayment - earlyRepayment;

                document.getElementById('ms_principal').textContent = formatNumber(principal);
                document.getElementById('ms_excise').textContent = formatNumber(exciseDuty);
                document.getElementById('ms_deposit').textContent = formatNumber(depositAmount);
                document.getElementById('ms_facilitation').textContent = formatNumber(facilitationFee);
                document.getElementById('ms_standard').textContent = formatNumber(standardRepayment);
                document.getElementById('ms_refund').textContent = formatNumber(earlyRepaymentDiscount);
                document.getElementById('ms_early').textContent = formatNumber(earlyRepayment);
                document.getElementById('ms_savings').textContent = formatNumber(savings);

                return true;
            }

            // KCB M-Pesa breakdown calculator
            function calculateKcbBreakdown() {
                const principal = parseFloat(principalInput.value) || 0;

                if (principal <= 0) {
                    return null;
                }

                const facilityFeeRate = 0.0176; // 1.76%
                const interestRate = 7.05; // 7.05%

                const facilityFee = principal * facilityFeeRate;
                const interest = (principal * interestRate) / 100;
                const totalRepayment = principal + facilityFee + interest;

                document.getElementById('kcb_principal').textContent = formatNumber(principal);
                document.getElementById('kcb_deposit').textContent = formatNumber(principal);
                document.getElementById('kcb_facility').textContent = formatNumber(facilityFee);
                document.getElementById('kcb_facility_refund').textContent = formatNumber(facilityFee);
                document.getElementById('kcb_interest').textContent = formatNumber(interest);
                document.getElementById('kcb_total').textContent = formatNumber(totalRepayment);

                return true;
            }

            // Custom/Other loan breakdown calculator
            function calculateOtherBreakdown() {
                const principal = parseFloat(principalInput.value) || 0;
                const interestAmount = parseFloat(customInterestInput.value) || 0;

                if (principal <= 0) {
                    return null;
                }

                const totalRepayment = principal + interestAmount;

                document.getElementById('other_principal').textContent = formatNumber(principal);
                document.getElementById('other_deposit').textContent = formatNumber(principal);
                document.getElementById('other_interest').textContent = formatNumber(interestAmount);
                document.getElementById('other_total').textContent = formatNumber(totalRepayment);

                // Update note based on whether there's interest
                const noteEl = document.getElementById('other_note');
                if (interestAmount > 0) {
                    noteEl.textContent = '💰 Interest-bearing loan - Total to repay: KES ' + formatNumber(totalRepayment);
                } else {
                    noteEl.textContent = '✨ Interest-free loan - Only repay principal amount';
                }

                return true;
            }

            // Update breakdown display
            function updateBreakdown() {
                const loanType = loanTypeSelect.value;
                const principal = parseFloat(principalInput.value) || 0;

                if (principal <= 0) {
                    breakdownMshwari.classList.add('hidden');
                    breakdownKcb.classList.add('hidden');
                    breakdownOther.classList.add('hidden');
                    breakdownEmpty.classList.remove('hidden');
                    return;
                }

                breakdownEmpty.classList.add('hidden');

                if (loanType === 'kcb_mpesa') {
                    calculateKcbBreakdown();
                    breakdownKcb.classList.remove('hidden');
                    breakdownMshwari.classList.add('hidden');
                    breakdownOther.classList.add('hidden');
                } else if (loanType === 'other') {
                    calculateOtherBreakdown();
                    breakdownOther.classList.remove('hidden');
                    breakdownMshwari.classList.add('hidden');
                    breakdownKcb.classList.add('hidden');
                } else {
                    calculateMshwariBreakdown();
                    breakdownMshwari.classList.remove('hidden');
                    breakdownKcb.classList.add('hidden');
                    breakdownOther.classList.add('hidden');
                }
            }

            function formatNumber(num) {
                return num.toLocaleString('en-US', {
                    minimumFractionDigits: 0,
                    maximumFractionDigits: 2
                });
            }

            // Event listeners
            principalInput.addEventListener('input', updateBreakdown);
            loanTypeSelect.addEventListener('change', () => {
                toggleCustomRates();
                updateDueDate();
                updateBreakdown();
            });
            customInterestInput.addEventListener('input', updateBreakdown);
            disbursedDateInput.addEventListener('change', updateDueDate);

            // Initialize on page load
            toggleCustomRates();
            updateDueDate();
            updateBreakdown();
        });
    </script>
</x-app-layout>
