@props(['accounts'])

<div class="p-6 bg-white dark:bg-gray-800 rounded-lg shadow">
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 mb-4">Accounts</h3>
    <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        @foreach($accounts as $account)
            <li class="py-2 flex justify-between items-center">
                <span class="text-gray-700 dark:text-gray-300">{{ $account->name }}</span>
                <span class="font-bold text-gray-900 dark:text-gray-100">KES {{ number_format($account->balance,0,'.',',') }}</span>
            </li>
        @endforeach
    </ul>
</div>
