<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Reverse Top-Up
            </h2>
            <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'topups']) }}"
               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-lg mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">

                <div class="flex items-center gap-3 mb-6 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg border border-red-200 dark:border-red-800">
                    <svg class="w-6 h-6 text-red-600 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    <p class="text-sm text-red-700 dark:text-red-300">
                        This will permanently remove this top-up and recalculate your account balance.
                    </p>
                </div>

                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Transaction Details</h3>

                <dl class="space-y-3 mb-6">
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Account</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $account->name }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Date</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $transaction->date->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Description</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $transaction->description }}</dd>
                    </div>
                    <div class="flex justify-between text-sm">
                        <dt class="text-gray-500 dark:text-gray-400">Category</dt>
                        <dd class="font-medium text-gray-900 dark:text-white">{{ $transaction->category->name }}</dd>
                    </div>
                    <div class="flex justify-between text-sm border-t dark:border-gray-700 pt-3">
                        <dt class="text-gray-500 dark:text-gray-400">Amount to Reverse</dt>
                        <dd class="font-bold text-red-600 dark:text-red-400 text-base">
                            −KES {{ number_format($transaction->amount, 0, '.', ',') }}
                        </dd>
                    </div>
                </dl>

                <form method="POST"
                      action="{{ route('accounts.topup.reverse', ['account' => $account, 'transaction' => $transaction]) }}">
                    @csrf
                    @method('DELETE')

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Reason <span class="text-gray-400 font-normal">(optional)</span>
                        </label>
                        <textarea name="reason" rows="2"
                                  placeholder="e.g. Entered by mistake"
                                  class="w-full text-sm rounded-lg border border-gray-300 dark:border-gray-600
                                         dark:bg-gray-700 dark:text-white px-3 py-2
                                         focus:ring-2 focus:ring-red-500 focus:border-red-500"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'topups']) }}"
                           class="flex-1 text-center px-4 py-2.5 rounded-lg border border-gray-300 dark:border-gray-600
                                  text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 text-sm font-medium transition">
                            Cancel
                        </a>
                        <button type="submit"
                                class="flex-1 bg-red-600 text-white px-4 py-2.5 rounded-lg hover:bg-red-700
                                       transition text-sm font-medium shadow-md">
                            Confirm Reversal
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
