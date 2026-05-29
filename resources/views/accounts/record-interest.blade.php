<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Record Monthly Interest') }}
            </h2>
            <a href="{{ route('accounts.show', $account) }}"
               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back to {{ $account->name }}
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

            {{-- Flash Messages --}}
            @if(session('error'))
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Last Recording Info --}}
            @if($lastInterestDate)
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800 dark:text-blue-200">
                        <span class="font-semibold">Last recorded:</span> {{ $lastInterestDate->format('M Y') }}
                        <span class="text-gray-500 dark:text-gray-400">({{ $lastInterestDate->diffForHumans() }})</span>
                    </p>
                </div>
            @else
                <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                    <p class="text-sm text-green-800 dark:text-green-200">
                        This is the first time you're recording interest for {{ $account->name }}.
                    </p>
                </div>
            @endif

            {{-- Form --}}
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-lg p-6">
                <form action="{{ route('accounts.interest.store', $account) }}" method="POST">
                    @csrf

                    <div class="space-y-6">

                        {{-- Skipped Months Alert --}}
                        @if($skippedDaysCount !== null && $skippedDaysCount > 0 && $skippedDateRange)
                            <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                                <div class="flex gap-3">
                                    <svg class="w-5 h-5 text-amber-600 dark:text-amber-500 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <div class="flex-1">
                                        <h3 class="font-semibold text-amber-900 dark:text-amber-100 mb-2">
                                            Skipped {{ $skippedDaysCount }} {{ $skippedDaysCount === 1 ? 'Month' : 'Months' }}
                                        </h3>
                                        <p class="text-sm text-amber-800 dark:text-amber-200 mb-2">
                                            The last interest was recorded in <span class="font-medium">{{ $skippedDateRange['last_recorded'] }}</span>.
                                        </p>
                                        <p class="text-sm text-amber-800 dark:text-amber-200 mb-3">
                                            You are about to record interest covering
                                            <span class="font-semibold">{{ $skippedDateRange['gap_start'] }} through {{ now()->format('M Y') }}</span>
                                            — a total of <span class="font-semibold">{{ $skippedDateRange['total_days'] }} {{ $skippedDateRange['total_days'] === 1 ? 'month' : 'months' }}</span>.
                                        </p>

                                        {{-- Acknowledgment Checkbox --}}
                                        <label class="flex items-center gap-2 cursor-pointer">
                                            <input type="checkbox"
                                                   id="acknowledge_skipped"
                                                   name="acknowledge_skipped"
                                                   value="1"
                                                   class="rounded border-gray-300 dark:border-gray-600 dark:bg-gray-800 text-amber-600 focus:ring-amber-500">
                                            <span class="text-sm text-amber-900 dark:text-amber-200">
                                                I confirm this interest covers all <span class="font-semibold">{{ $skippedDateRange['total_days'] }}</span> {{ $skippedDateRange['total_days'] === 1 ? 'month' : 'months' }}
                                            </span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Amount --}}
                        <div>
                            <label for="amount" class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Interest Amount <span class="text-red-600">*</span>
                            </label>
                            <div class="relative">
                                <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-gray-600 dark:text-gray-400 font-medium">
                                    KES
                                </span>
                                <input type="number"
                                       id="amount"
                                       name="amount"
                                       step="0.01"
                                       min="0.01"
                                       required
                                       value="{{ old('amount') }}"
                                       placeholder="0.00"
                                       class="w-full pl-12 pr-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            @error('amount')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Date --}}
                        <div>
                            <label for="date" class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Recording Date <span class="text-red-600">*</span>
                            </label>
                            <input type="datetime-local" id="date" name="date" required
                                   value="{{ old('date', now()->format('Y-m-d\TH:i')) }}"
                                   class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            @error('date')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                The date this monthly interest is being recorded
                            </p>
                        </div>

                        {{-- Description (Optional) --}}
                        <div>
                            <label for="description" class="block text-sm font-semibold text-gray-900 dark:text-white mb-2">
                                Description <span class="text-gray-500 text-xs font-normal">Optional</span>
                            </label>
                            <textarea id="description"
                                      name="description"
                                      rows="3"
                                      maxlength="255"
                                      placeholder="e.g. 'May 2026 interest payout'..."
                                      class="w-full px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">{{ old('description') }}</textarea>
                            @error('description')
                            <p class="text-red-600 dark:text-red-400 text-sm mt-1">{{ $message }}</p>
                            @enderror
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                Leave blank to auto-generate: "Interest earned – {{ $targetMonth->format('M Y') }}"
                            </p>
                        </div>

                        {{-- Preview --}}
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <p class="text-xs font-semibold text-gray-600 dark:text-gray-400 mb-2">Preview</p>
                            <div class="space-y-1">
                                <p class="text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium">Account:</span> {{ $account->name }}
                                </p>
                                <p class="text-sm text-gray-900 dark:text-white">
                                    <span class="font-medium">Amount:</span>
                                    <span id="preview-amount" class="text-emerald-600 dark:text-emerald-400 font-semibold">
                                        KES {{ number_format(0, 0) }}
                                    </span>
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <span class="font-medium">Description:</span>
                                    <span id="preview-desc" class="italic">Interest earned – {{ $targetMonth->format('M Y') }}</span>
                                </p>
                            </div>
                        </div>

                    </div>

                    {{-- Action Buttons --}}
                    <div class="flex gap-3 mt-8">
                        <a href="{{ route('accounts.show', $account) }}"
                           class="flex-1 px-6 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition font-medium text-center">
                            Cancel
                        </a>
                        <button type="submit"
                                id="submit-btn"
                                @if($skippedDaysCount !== null && $skippedDaysCount > 0) disabled @endif
                                class="flex-1 px-6 py-2.5 rounded-lg font-medium text-white transition
           {{ ($skippedDaysCount !== null && $skippedDaysCount > 0)
               ? 'bg-gray-400 dark:bg-gray-600 cursor-not-allowed opacity-60'
               : 'bg-emerald-600 hover:bg-emerald-700' }}"
                        >
                            📈 Record Interest
                        </button>
                    </div>

                </form>
            </div>

        </div>
    </div>

    {{-- Live Preview Script --}}
    <script>
        const amountInput    = document.getElementById('amount');
        const descInput      = document.getElementById('description');
        const previewAmount  = document.getElementById('preview-amount');
        const previewDesc    = document.getElementById('preview-desc');
        const acknowledgeCheckbox = document.getElementById('acknowledge_skipped');
        const submitBtn      = document.getElementById('submit-btn');

        function updatePreview() {
            const amount = parseFloat(amountInput.value) || 0;
            previewAmount.textContent = 'KES ' + new Intl.NumberFormat('en-US').format(Math.round(amount));

            if (descInput.value.trim()) {
                previewDesc.textContent = descInput.value;
            } else {
                @if($skippedDaysCount !== null && $skippedDaysCount > 0)
                    previewDesc.textContent = 'Interest earned – {{ $targetMonth->format("M Y") }} ({{ $skippedDateRange["total_days"] }}-month period)';
                @else
                    previewDesc.textContent = 'Interest earned – {{ $targetMonth->format("M Y") }}';
                @endif
            }
        }

        amountInput.addEventListener('input', updatePreview);
        descInput.addEventListener('input', updatePreview);

        @if($skippedDaysCount !== null && $skippedDaysCount > 0)
        acknowledgeCheckbox.addEventListener('change', function () {
            submitBtn.disabled = !this.checked;
            submitBtn.classList.toggle('opacity-60', !this.checked);
            submitBtn.classList.toggle('cursor-not-allowed', !this.checked);
            submitBtn.classList.toggle('bg-gray-400', !this.checked);
            submitBtn.classList.toggle('dark:bg-gray-600', !this.checked);
            submitBtn.classList.toggle('bg-emerald-600', this.checked);
            submitBtn.classList.toggle('dark:bg-emerald-600', this.checked);
        });
        @endif

        updatePreview();
    </script>
</x-app-layout>
