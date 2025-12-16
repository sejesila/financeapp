@props(['loans'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Active Loans</h3>
    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($loans as $loan)
            <li class="py-2 flex justify-between items-center">
                <div>
                    <span class="text-gray-700 dark:text-gray-300">{{ $loan->name }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">({{ $loan->due_date->format('M d, Y') }})</span>
                </div>
                <span class="font-bold text-red-600 dark:text-red-400">KES {{ number_format($loan->amount,0,'.',',') }}</span>
            </li>
        @endforeach
    </ul>
</div>
