<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Reverse Transfer
            </h2>
            <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'transfers']) }}"
               class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400">
                ← Back
            </a>
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-lg mx-auto px-4">
            <div class="bg-white dark:bg-gray-800 shadow-lg rounded-xl p-6">

                <div class="mb-6 p-4 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                    <p class="text-sm text-red-700 dark:text-red-400 font-medium">⚠️ You are about to reverse this transfer.</p>
                    <p class="text-xs text-red-600 dark:text-red-500 mt-1">This will undo the transfer and recalculate both account balances.</p>
                </div>

                <dl class="space-y-3 mb-6 text-sm">
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Amount</dt>
                        <dd class="font-semibold text-gray-900 dark:text-white">KES {{ number_format($transfer->amount, 0, '.', ',') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">From</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $transfer->fromAccount->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">To</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $transfer->toAccount->name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Date</dt>
                        <dd class="text-gray-900 dark:text-white">{{ $transfer->date->format('M d, Y') }}</dd>
                    </div>
                    @if($transfer->description)
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Description</dt>
                            <dd class="text-gray-900 dark:text-white">{{ $transfer->description }}</dd>
                        </div>
                    @endif
                </dl>

                <form method="POST"
                      action="{{ route('accounts.transfer.reverse', ['account' => $account, 'transfer' => $transfer]) }}">
                    @csrf

                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                            Reason (optional)
                        </label>
                        <textarea name="reason" rows="3"
                                  class="w-full rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm px-3 py-2 focus:ring-2 focus:ring-red-500"
                                  placeholder="Why are you reversing this transfer?"></textarea>
                    </div>

                    <div class="flex gap-3">
                        <button type="submit"
                                class="flex-1 bg-red-600 text-white py-2.5 rounded-lg hover:bg-red-700 transition font-medium text-sm">
                            Confirm Reversal
                        </button>
                        <a href="{{ route('accounts.show', ['account' => $account, 'tab' => 'transfers']) }}"
                           class="flex-1 text-center bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 py-2.5 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition font-medium text-sm">
                            Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</x-app-layout>
