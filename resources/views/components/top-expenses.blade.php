@props(['expenses'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Top Expenses</h3>
    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($expenses as $expense)
            <li class="py-2 flex justify-between items-center">
                <span class="text-gray-700 dark:text-gray-300">
                    {{ $expense->category->name ?? 'Unknown' }}
                </span>
                <span class="font-bold text-red-600 dark:text-red-400">
                    KES {{ number_format($expense->total ?? 0,0,'.',',') }}
                </span>
            </li>
        @endforeach
    </ul>
</div>
