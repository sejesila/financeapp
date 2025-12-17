@props(['accounts', 'href' => '#'])

<div class="mb-6">
    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Accounts</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($accounts as $account)
            <a href="{{ $href }}" class="block transform transition-all duration-300 hover:scale-105">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <h4 class="text-md font-semibold text-gray-800 dark:text-white">{{ $account->name ?? 'Unnamed Account' }}</h4>
                    <p class="text-sm font-bold text-gray-900 dark:text-white">
                        Balance: KES {{ number_format($account->balance ?? 0, 0, '.', ',') }}
                    </p>
                </div>
            </a>
        @endforeach
    </div>
</div>
