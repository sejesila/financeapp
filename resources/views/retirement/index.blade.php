<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-lg sm:text-xl text-gray-800 dark:text-gray-200 leading-tight">Retirement Funds</h2>
    </x-slot>

    <div class="py-3 sm:py-8">
        <div class="max-w-4xl mx-auto px-3 sm:px-6 lg:px-8 space-y-4">

            @foreach (['success' => 'green', 'error' => 'red'] as $key => $color)
                @if(session($key))
                    <div class="bg-{{ $color }}-100 text-{{ $color }}-700 p-3 rounded text-sm">{{ session($key) }}</div>
                @endif
            @endforeach
            @if($errors->any())
                <div class="bg-red-100 text-red-700 p-3 rounded text-sm">
                    <ul class="list-disc list-inside">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                </div>
            @endif

            <div class="bg-gradient-to-r from-slate-600 to-indigo-700 text-white p-4 sm:p-6 rounded-lg shadow-lg">
                <p class="text-xs sm:text-sm font-semibold">🏦 Total Retirement Savings</p>
                <p class="text-2xl sm:text-4xl font-bold mt-1">KES {{ number_format($total, 0, '.', ',') }}</p>
                <p class="text-xs mt-1 opacity-90">Across {{ $funds->count() }} {{ Str::plural('fund', $funds->count()) }}. </p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($funds as $fund)
                    <a href="{{ route('retirement.show', $fund) }}"
                       class="block bg-white dark:bg-gray-800 rounded-lg shadow-md p-4 hover:shadow-lg transition">
                        <p class="text-xs uppercase tracking-wide text-gray-500">{{ $fund->type === 'nssf' ? 'NSSF' : 'Provident Fund' }}</p>
                        <h3 class="font-semibold text-gray-800 dark:text-gray-200">{{ $fund->name }}</h3>
                        <p class="text-2xl font-bold text-indigo-600 dark:text-indigo-400 mt-2">
                            KES {{ number_format($fund->balance(), 0, '.', ',') }}
                        </p>
                        <p class="text-xs text-gray-500 mt-1">
                            Opening KES {{ number_format($fund->opening_balance, 0) }}
                            @if($fund->opening_balance_date) as at {{ $fund->opening_balance_date->format('M d, Y') }} @endif
                        </p>
                    </a>
                @endforeach
            </div>

            {{-- Add fund: hidden once the maximum number of funds exists --}}
            @if($canAddFund)
                <details class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-4" {{ $funds->isEmpty() ? 'open' : '' }}>
                    <summary class="cursor-pointer text-sm font-semibold text-gray-800 dark:text-gray-200">+ Add a fund</summary>
                    <form method="POST" action="{{ route('retirement.store') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                        @csrf
                        <label class="block">Name
                            <input name="name" required value="{{ old('name') }}" placeholder="Staff Provident Fund"
                                   class="mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </label>
                        <label class="block">Type
                            <select name="type" class="mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="provident">Provident fund</option>
                                <option value="nssf">NSSF</option>
                            </select>
                        </label>
                        <label class="block">Opening balance (from your latest statement)
                            <input name="opening_balance" type="number" step="0.01" min="0" required value="{{ old('opening_balance') }}"
                                   class="mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </label>
                        <label class="block">Balance as at
                            <input name="opening_balance_date" type="date" required value="{{ old('opening_balance_date') }}"
                                   class="mt-1 w-full rounded border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                        </label>
                        <div class="sm:col-span-2">
                            <button class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded text-sm">Create fund</button>
                        </div>
                    </form>
                </details>
            @endif
        </div>
    </div>
</x-app-layout>
