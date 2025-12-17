@props(['accounts', 'href' => '#'])

<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
    @forelse($accounts as $account)
        <a href="{{ $href }}" class="block transform transition-all duration-300 hover:scale-105">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4 animate-fade-in">
                <h4 class="text-md font-semibold text-gray-800 dark:text-white">{{ $account->name ?? 'Unnamed Account' }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Balance: KES {{ number_format($account->current_balance ?? 0, 0, '.', ',') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Status: {{ $account->is_active ? 'Active' : 'Inactive' }}
                </p>
            </div>
        </a>
    @empty
        <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl shadow p-4 text-center text-gray-500 dark:text-gray-400">
            No accounts found.
        </div>
    @endforelse
</div>
