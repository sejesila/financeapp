@props(['loans', 'href' => '#'])

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    @forelse($loans as $loan)
        <a href="{{ $href }}" class="block transform transition-all duration-300 hover:scale-105">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow p-4">
                <h4 class="text-md font-semibold text-gray-800 dark:text-white">{{ $loan->name ?? $loan->loan_name ?? 'Unnamed Loan' }}</h4>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Amount: KES {{ number_format($loan->balance ?? 0, 0, '.', ',') }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Due: {{ $loan->due_date ? $loan->due_date->format('M d, Y') : 'N/A' }}
                </p>
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Status: {{ ucfirst($loan->status ?? 'N/A') }}
                </p>
            </div>
        </a>
    @empty
        <div class="col-span-full bg-white dark:bg-gray-800 rounded-xl shadow p-4 text-center text-gray-500 dark:text-gray-400">
            No active loans found.
        </div>
    @endforelse
</div>
