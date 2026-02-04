<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Edit Account') }}
            </h2>
            <a href="{{ route('accounts.show', $account) }}" class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back to Account
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Errors -->
            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-4 rounded-lg mb-6">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">
                <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-6">Edit Account Details</h3>

                <form action="{{ route('accounts.update', $account) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <!-- Account Type (Read-only) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Account Type
                        </label>
                        <div class="flex items-center gap-3 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <!-- Logo based on account type -->
                            <div class="w-12 h-12 rounded-full flex items-center justify-center bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900">
                                @if(strtolower($account->type) === 'bank')
                                    <img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-10 h-10 object-contain rounded-full">
                                @elseif(strtolower($account->type) === 'mpesa')
                                    <img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-10 h-10 object-contain">
                                @elseif(strtolower($account->type) === 'airtel_money')
                                    <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-10 h-10 object-contain">
                                @else
                                    <span class="font-bold text-xl text-gray-700 dark:text-gray-300">{{ substr($account->name, 0, 1) }}</span>
                                @endif
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900 dark:text-white capitalize">
                                    {{ str_replace('_', ' ', $account->type) }}
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Account type cannot be changed</p>
                            </div>
                        </div>
                    </div>

                    <!-- Account Logo/Icon Upload -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Account Logo/Icon (Optional)
                        </label>

                        <div class="flex items-start gap-4">
                            <!-- Current Logo Preview -->
                            <div class="flex-shrink-0">
                                <div id="logo-preview" class="w-20 h-20 rounded-full flex items-center justify-center bg-gradient-to-br from-blue-100 to-indigo-100 dark:from-blue-900 dark:to-indigo-900 border-2 border-gray-300 dark:border-gray-600">
                                    @if($account->logo_path)
                                        <img src="{{ asset('storage/' . $account->logo_path) }}" alt="{{ $account->name }}" class="w-full h-full object-cover rounded-full">
                                    @elseif(strtolower($account->type) === 'bank')
                                        <img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-16 h-16 object-contain rounded-full">
                                    @elseif(strtolower($account->type) === 'mpesa')
                                        <img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-16 h-16 object-contain">
                                    @elseif(strtolower($account->type) === 'airtel_money')
                                        <img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-16 h-16 object-contain">
                                    @else
                                        <span class="font-bold text-2xl text-gray-700 dark:text-gray-300">{{ substr($account->name, 0, 1) }}</span>
                                    @endif
                                </div>
                            </div>

                            <div class="flex-1">
                                <!-- File Input -->
                                <input
                                    type="file"
                                    name="logo"
                                    id="logo"
                                    accept="image/*"
                                    class="block w-full text-sm text-gray-500 dark:text-gray-400
                                        file:mr-4 file:py-2 file:px-4
                                        file:rounded-lg file:border-0
                                        file:text-sm file:font-semibold
                                        file:bg-indigo-50 file:text-indigo-700
                                        hover:file:bg-indigo-100
                                        dark:file:bg-indigo-900 dark:file:text-indigo-300
                                        dark:hover:file:bg-indigo-800"
                                    onchange="previewLogo(event)">

                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                    Upload a custom logo or icon for this account. Recommended: Square image, max 2MB.
                                </p>

                                <!-- Remove Logo Checkbox -->
                                @if($account->logo_path)
                                    <div class="mt-3">
                                        <label class="flex items-center gap-2">
                                            <input
                                                type="checkbox"
                                                name="remove_logo"
                                                value="1"
                                                class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                                onchange="toggleLogoRemoval(this)">
                                            <span class="text-sm text-gray-700 dark:text-gray-300">Remove current logo</span>
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Account Name -->
                    <div class="mb-6">
                        <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Account Name <span class="text-red-500">*</span>
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="name"
                            value="{{ old('name', $account->name) }}"
                            required
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white"
                            placeholder="e.g., Main M-Pesa Account">
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Give your account a descriptive name</p>
                    </div>

                    <!-- Notes (Optional) -->
                    <div class="mb-6">
                        <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Notes (Optional)
                        </label>
                        <textarea
                            name="notes"
                            id="notes"
                            rows="3"
                            class="w-full px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-indigo-500 dark:bg-gray-700 dark:text-white"
                            placeholder="Add any additional notes about this account">{{ old('notes', $account->notes) }}</textarea>
                    </div>

                    <!-- Initial Balance (Read-only) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Initial Balance
                        </label>
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <p class="text-lg font-semibold text-gray-900 dark:text-white">
                                KES {{ number_format($account->initial_balance, 0, '.', ',') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Initial balance cannot be changed</p>
                        </div>
                    </div>

                    <!-- Current Balance (Read-only) -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            Current Balance
                        </label>
                        <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600">
                            <p class="text-lg font-semibold {{ $account->current_balance >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                KES {{ number_format($account->current_balance, 0, '.', ',') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Balance is calculated automatically from transactions</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('accounts.show', $account) }}"
                           class="px-6 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition font-medium">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>

            <!-- Warning Box -->
            <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                <div class="flex gap-3">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div>
                        <p class="text-sm font-semibold text-blue-900 dark:text-blue-200 mb-1">Why can't I change everything?</p>
                        <p class="text-sm text-blue-800 dark:text-blue-300">
                            Account type and balances are locked to maintain data integrity. Your account balance is automatically calculated from all transactions, so it cannot be manually edited.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function previewLogo(event) {
            const file = event.target.files[0];
            const preview = document.getElementById('logo-preview');

            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" class="w-full h-full object-cover rounded-full">`;
                }
                reader.readAsDataURL(file);

                // Uncheck remove logo if file is selected
                const removeCheckbox = document.querySelector('input[name="remove_logo"]');
                if (removeCheckbox) {
                    removeCheckbox.checked = false;
                }
            }
        }

        function toggleLogoRemoval(checkbox) {
            if (checkbox.checked) {
                // Clear file input if remove is checked
                const fileInput = document.getElementById('logo');
                fileInput.value = '';

                // Show default logo
                const preview = document.getElementById('logo-preview');
                const accountType = '{{ strtolower($account->type) }}';

                if (accountType === 'bank') {
                    preview.innerHTML = '<img src="{{ asset('images/imbank.jpeg') }}" alt="I&M Bank" class="w-16 h-16 object-contain rounded-full">';
                } else if (accountType === 'mpesa') {
                    preview.innerHTML = '<img src="{{ asset('images/mpesa.png') }}" alt="M-Pesa" class="w-16 h-16 object-contain">';
                } else if (accountType === 'airtel_money') {
                    preview.innerHTML = '<img src="{{ asset('images/airtel-money.png') }}" alt="Airtel Money" class="w-16 h-16 object-contain">';
                } else {
                    const initial = '{{ substr($account->name, 0, 1) }}';
                    preview.innerHTML = `<span class="font-bold text-2xl text-gray-700 dark:text-gray-300">${initial}</span>`;
                }
            }
        }
    </script>
</x-app-layout>
