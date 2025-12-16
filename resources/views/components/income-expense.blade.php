@props(['title', 'income', 'expenses', 'net', 'period'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">{{ $period }}</h3>
    <div class="mt-4 space-y-2">
        <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-300">Income</span>
            <span class="text-green-600 dark:text-green-400">KES {{ number_format($income,0,'.',',') }}</span>
        </div>
        <div class="flex justify-between">
            <span class="text-gray-600 dark:text-gray-300">Expenses</span>
            <span class="text-red-600 dark:text-red-400">KES {{ number_format($expenses,0,'.',',') }}</span>
        </div>
        <div class="flex justify-between font-bold">
            <span>Net</span>
            <span class="{{ $net >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400' }}">
                KES {{ number_format($net,0,'.',',') }}
            </span>
        </div>
    </div>
</div>
