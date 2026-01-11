<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
            <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Transaction Details') }}
            </h2>
            <div class="flex items-center gap-2 sm:gap-3">
                @if(!$transaction->is_transaction_fee)
                    <a href="{{ route('transactions.edit', $transaction) }}"
                       class="inline-flex items-center px-3 sm:px-4 py-1.5 sm:py-2 bg-indigo-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                        <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                        </svg>
                        Edit
                    </a>
                @endif
                <a href="{{ route('transactions.index') }}"
                   class="inline-flex items-center text-xs sm:text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    {{ __('Back') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        <!-- Flash Messages -->
        @if(session('success'))
            <div class="bg-green-50 border-l-4 border-green-400 text-green-800 p-3 sm:p-4 rounded-lg mb-4 sm:mb-6 flex items-start text-sm" role="alert">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 sm:mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if(session('error'))
            <div class="bg-red-50 border-l-4 border-red-400 text-red-800 p-3 sm:p-4 rounded-lg mb-4 sm:mb-6 flex items-start text-sm" role="alert">
                <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2 sm:mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <span>{{ session('error') }}</span>
            </div>
        @endif

        <!-- Transaction Details Card -->
        <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl overflow-hidden">
            <!-- Amount Header (Featured) -->
            <div class="text-center px-4 sm:px-6 pt-6 sm:pt-8 pb-4 sm:pb-6 bg-gradient-to-br {{ $transaction->category->type === 'income' ? 'from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20' : 'from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20' }}">
                <time datetime="{{ $transaction->date->format('Y-m-d') }}" class="block text-xs sm:text-sm text-gray-600 dark:text-gray-400 mb-2 sm:mb-3 font-medium">
                    {{ $transaction->date->format('l, F j, Y') }}
                </time>

                <p class="font-bold text-3xl sm:text-4xl md:text-5xl {{ $transaction->category->type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }} mb-2 sm:mb-3">
                    {{ $transaction->category->type === 'income' ? '+' : '−' }}
                    <span class="tracking-tight">KES {{ number_format($transaction->amount, 0, '.', ',') }}</span>
                </p>

                @if($transaction->description)
                    <p class="text-gray-700 dark:text-gray-300 text-base sm:text-lg max-w-2xl mx-auto px-2">
                        {{ $transaction->description }}
                    </p>
                @endif
            </div>

            <!-- Details Section -->
            <div class="px-4 sm:px-6 py-4 sm:py-6">
                <h3 class="text-xs sm:text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-3 sm:mb-4">
                    {{ __('Transaction Information') }}
                </h3>

                <dl class="space-y-3 sm:space-y-4">
                    <!-- Account -->
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700">
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                            {{ __('Account') }}
                        </dt>
                        <dd class="text-xs sm:text-sm">
                            <a href="{{ route('accounts.show', $transaction->account) }}"
                               class="inline-flex items-center text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-semibold transition-colors">
                                {{ $transaction->account->name }}
                                <svg class="w-3 h-3 sm:w-4 sm:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </a>
                        </dd>
                    </div>

                    <!-- Category -->
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700">
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                            {{ __('Category') }}
                        </dt>
                        <dd class="text-xs sm:text-sm">
                            <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs sm:text-sm font-semibold {{ $transaction->category->type === 'income' ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' }}">
                                {{ $transaction->category->name }}
                            </span>
                        </dd>
                    </div>

                    <!-- Transaction Fee Badge (if applicable) -->
                    @if($transaction->is_transaction_fee)
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700">
                            <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                                {{ __('Type') }}
                            </dt>
                            <dd class="text-xs sm:text-sm">
                                <span class="inline-flex items-center px-2 sm:px-3 py-1 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                    💸 Transaction Fee
                                </span>
                            </dd>
                        </div>
                    @endif

                    <!-- Related Fee (if main transaction has a fee) -->
                    @if($transaction->related_fee_transaction_id && !$transaction->is_transaction_fee)
                        <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700">
                            <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                                {{ __('Transaction Fee') }}
                            </dt>
                            <dd class="text-xs sm:text-sm">
                                <a href="{{ route('transactions.show', $transaction->related_fee_transaction_id) }}"
                                   class="inline-flex items-center text-yellow-600 hover:text-yellow-800 dark:text-yellow-400 dark:hover:text-yellow-300 font-semibold transition-colors">
                                    KES {{ number_format($transaction->feeTransaction->amount ?? 0, 0, '.', ',') }}
                                    <svg class="w-3 h-3 sm:w-4 sm:h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                    </svg>
                                </a>
                            </dd>
                        </div>
                    @endif

                    <!-- Transaction ID -->
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3 border-b border-gray-200 dark:border-gray-700">
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                            {{ __('Transaction ID') }}
                        </dt>
                        <dd class="text-xs sm:text-sm font-mono text-gray-700 dark:text-gray-300">
                            #{{ str_pad($transaction->id, 6, '0', STR_PAD_LEFT) }}
                        </dd>
                    </div>

                    <!-- Recorded -->
                    <div class="flex flex-col sm:flex-row sm:justify-between sm:items-center py-2 sm:py-3">
                        <dt class="text-xs sm:text-sm font-medium text-gray-500 dark:text-gray-400 mb-1 sm:mb-0">
                            {{ __('Recorded') }}
                        </dt>
                        <dd class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">
                            <time datetime="{{ $transaction->created_at->toIso8601String() }}">
                                {{ $transaction->created_at->format('F j, Y \a\t g:i A') }}
                            </time>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        <!-- Info Note -->
        @if($transaction->is_transaction_fee)
            <div class="mt-4 sm:mt-6 bg-yellow-50 dark:bg-yellow-900/20 border-l-4 border-yellow-500 dark:border-yellow-400 p-3 sm:p-4 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-yellow-500 dark:text-yellow-400 mr-2 sm:mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-yellow-900 dark:text-yellow-200 mb-1">
                            {{ __('System Generated Fee') }}
                        </p>
                        <p class="text-xs sm:text-sm text-yellow-800 dark:text-yellow-300">
                            {{ __('This is an automatically generated transaction fee. It cannot be edited directly. Edit the main transaction to update the fee.') }}
                        </p>
                    </div>
                </div>
            </div>
        @else
            <div class="mt-4 sm:mt-6 bg-blue-50 dark:bg-blue-900/20 border-l-4 border-blue-500 dark:border-blue-400 p-3 sm:p-4 rounded-lg">
                <div class="flex items-start">
                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-blue-500 dark:text-blue-400 mr-2 sm:mr-3 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <div>
                        <p class="text-xs sm:text-sm font-medium text-blue-900 dark:text-blue-200 mb-1">
                            {{ __('Edit Transaction') }}
                        </p>
                        <p class="text-xs sm:text-sm text-blue-800 dark:text-blue-300">
                            {{ __('You can edit this transaction if you made a mistake. Account balances will be automatically recalculated.') }}
                        </p>
                    </div>
                </div>
            </div>
        @endif
        <!-- Add this to transactions/show.blade.php after the Edit button -->

        @if(!$transaction->is_transaction_fee)
            <form action="{{ route('transactions.destroy', $transaction) }}"
                  method="POST"
                  class="inline-block"
                  onsubmit="return confirm('Are you sure you want to delete this transaction? This will update your account balance.');">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="inline-flex items-center px-3 sm:px-4 py-1.5 sm:py-2 bg-red-600 text-white text-xs sm:text-sm font-medium rounded-lg hover:bg-red-700 transition-colors">
                    <svg class="w-3 h-3 sm:w-4 sm:h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Delete
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
