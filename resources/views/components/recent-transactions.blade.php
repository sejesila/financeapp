@props(['transactions'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Recent Transactions</h3>
    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($transactions as $txn)
            <li class="py-2 flex justify-between items-center">
                <div>
                    <span class="text-gray-700 dark:text-gray-300">{{ $txn->description }}</span>
                    <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">{{ $txn->date->format('M d') }}</span>
                </div>
                <span class="font-bold {{ $txn->type == 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                    KES {{ number_format($txn->amount,0,'.',',') }}
                </span>
            </li>
        @endforeach
    </ul>
</div>
