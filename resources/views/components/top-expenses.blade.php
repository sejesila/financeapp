@props(['expenses', 'href' => '#'])

<div class="mb-6">
    <h3 class="text-lg font-bold text-gray-800 dark:text-white mb-4">Top Expenses</h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-6">
        @foreach($expenses as $expense)
            <a href="{{ $href }}" class="block transform transition-all duration-300 hover:scale-105">
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                    <h4 class="text-md font-semibold text-gray-800 dark:text-white">{{ $expense->name }}</h4>
                    <p class="text-sm text-gray-500 dark:text-gray-400">Type: {{ ucfirst($expense->type) }}</p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Added: {{ $expense->created_at ? $expense->created_at->format('M d, Y') : 'N/A' }}
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-400">
                        Total: KES {{ number_format($expense->total, 0, '.', ',') }}
                    </p>

                </div>
            </a>
        @endforeach
    </div>
</div>
