@props(['current', 'previous', 'comparison', 'percent'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow mb-10">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Month Comparison</h3>
    <div class="grid grid-cols-3 gap-4">
        <div class="text-center">
            <span class="block text-gray-600 dark:text-gray-300">Previous Month</span>
            <span class="block font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($previous,0,'.',',') }}</span>
        </div>
        <div class="text-center">
            <span class="block text-gray-600 dark:text-gray-300">Current Month</span>
            <span class="block font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($current,0,'.',',') }}</span>
        </div>
        <div class="text-center">
            <span class="block text-gray-600 dark:text-gray-300">Change</span>
            <span class="block font-bold {{ $comparison >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                {{ $comparison >= 0 ? '+' : '' }}KES {{ number_format($comparison,0,'.',',') }} ({{ $percent }}%)
            </span>
        </div>
    </div>
</div>
